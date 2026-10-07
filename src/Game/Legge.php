<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * La legge della Federazione: notorieta', crimini e pattuglie.
 *
 * Ogni aggressione a civili alza la notorieta' del comandante, che cala da sola
 * col tempo (dimezza ogni legge.dimezza_ore) e cresce piu' in fretta per i
 * recidivi: ogni crimine commesso nelle ultime legge.recidiva_ore ore pesa
 * legge.recidiva_passo in piu' sul successivo. A gradini:
 *
 *   0 Incensurato · 1 Sospetto · 2 Ricercato · 3 Pericoloso · 4 Nemico pubblico
 *
 * Da Ricercato le pattuglie federali attaccano a vista, squadre
 * d'intercettazione tarate sulla sua nave gli danno la caccia, e chi lo abbatte
 * incassa una taglia federale. Da Pericoloso lo StarDock chiude i servizi.
 *
 * La notorieta' si legge sempre gia' decaduta (punti()): in tabella resta il
 * valore dell'ultimo crimine con il suo istante.
 */
final class Legge
{
    public const GRADI = [
        0 => 'Incensurato',
        1 => 'Sospetto',
        2 => 'Ricercato',
        3 => 'Pericoloso',
        4 => 'Nemico pubblico',
    ];

    public const RIASSUNTI = [
        0 => 'Nessun conto in sospeso con la Federazione.',
        1 => 'La Federazione ti tiene d\'occhio. Ancora un passo falso e diventi ricercato.',
        2 => 'Le pattuglie federali ti attaccano a vista e una squadra d\'intercettazione ti cerca. C\'e\' una taglia sulla tua testa.',
        3 => 'Lo StarDock ti ha chiuso i servizi. Due squadre d\'intercettazione ti danno la caccia.',
        4 => 'La Federazione ti vuole morto: tre squadre pesanti sulle tue tracce, in ogni fascia.',
    ];

    public const ETICHETTE = [
        'mercantile'    => 'Aggressione a un mercantile',
        'uccisione'     => 'Mercantile distrutto',
        'porto'         => 'Assalto a un porto',
        'pianeta'       => 'Assalto a un pianeta altrui',
        'bombardamento' => 'Bombardamento planetario',
        'aggressione'   => 'Aggressione a un comandante',
        'pattuglia'     => 'Attacco a una pattuglia federale',
    ];

    public const PATTUGLIA = 'Pattuglia federale';
    public const SOCCORSO = 'Pattuglia di soccorso';
    public const SQUADRA = 'Squadra d\'intercettazione';

    // --- notorieta' -----------------------------------------------------------

    /** @return list<int> soglie di Sospetto, Ricercato, Pericoloso, Nemico pubblico */
    public static function soglie(): array
    {
        $v = array_map('intval', explode(',', GameConfig::str('legge.soglie', '25,100,250,500')));
        sort($v);
        return count($v) === 4 ? $v : [25, 100, 250, 500];
    }

    /**
     * Notorieta' attuale, gia' decaduta.
     *
     * @param array<string,mixed> $player riga di players
     */
    public static function punti(array $player): float
    {
        $n = (float) ($player['notorieta'] ?? 0);
        if ($n <= 0 || empty($player['notorieta_at'])) {
            return max(0.0, $n);
        }
        $ore = max(0.0, (time() - (int) strtotime((string) $player['notorieta_at'])) / 3600);
        return $n * 0.5 ** ($ore / max(1, GameConfig::int('legge.dimezza_ore', 24)));
    }

    public static function puntiDi(int $playerId): float
    {
        $p = Database::first('SELECT notorieta, notorieta_at FROM players WHERE id = ?', [$playerId]);
        return $p === null ? 0.0 : self::punti($p);
    }

    public static function grado(float $punti): int
    {
        $g = 0;
        foreach (self::soglie() as $s) {
            if ($punti >= $s) {
                $g++;
            }
        }
        return $g;
    }

    /** @param array<string,mixed> $player */
    public static function gradoDi(array $player): int
    {
        return self::grado(isset($player['notorieta']) ? self::punti($player) : self::puntiDi((int) $player['id']));
    }

    /** @param array<string,mixed> $player */
    public static function ricercato(array $player): bool
    {
        return self::gradoDi($player) >= 2;
    }

    public static function nome(int $grado): string
    {
        return self::GRADI[max(0, min(4, $grado))];
    }

    /** Ore che servono perche' la notorieta' scenda sotto la soglia di Ricercato. */
    public static function oreAlPerdono(float $punti): float
    {
        $soglia = self::soglie()[1];
        if ($punti < $soglia) {
            return 0.0;
        }
        return log($punti / max(1, $soglia - 0.5), 2) * max(1, GameConfig::int('legge.dimezza_ore', 24));
    }

    /** Crimini commessi nelle ultime legge.recidiva_ore ore. */
    public static function recenti(int $playerId): int
    {
        return (int) (Database::first(
            'SELECT COUNT(*) n FROM crimini WHERE player_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)',
            [$playerId, max(1, GameConfig::int('legge.recidiva_ore', 72))]
        )['n'] ?? 0);
    }

    /** Peso di base di un crimine. */
    public static function peso(string $kind): int
    {
        return match ($kind) {
            'mercantile'    => GameConfig::int('legge.crimine_mercantile', 15),
            'uccisione'     => GameConfig::int('legge.crimine_uccisione', 30),
            'porto'         => GameConfig::int('legge.crimine_porto', 40),
            'pianeta'       => GameConfig::int('legge.crimine_pianeta', 20),
            'bombardamento' => GameConfig::int('legge.crimine_bombardamento', 50),
            'aggressione'   => GameConfig::int('legge.crimine_aggressione', 25),
            'pattuglia'     => GameConfig::int('legge.crimine_pattuglia', 80),
            default         => 0,
        };
    }

    /**
     * Registra un crimine e alza la notorieta'.
     *
     * La recidiva moltiplica: ogni crimine recente pesa il passo in piu' sul
     * successivo. Un transponder contraffatto (modulo) ne toglie una parte.
     * Il conto si fa in una sola istruzione, cosi' due crimini insieme non si
     * cancellano a vicenda.
     *
     * @return array{punti:float, aggiunti:float, grado:int, salito:bool}
     */
    public static function crimine(int $playerId, string $kind, ?int $sectorId = null): array
    {
        $prima = self::puntiDi($playerId);
        $base = self::peso($kind);
        if ($base <= 0) {
            return ['punti' => $prima, 'aggiunti' => 0.0, 'grado' => self::grado($prima), 'salito' => false];
        }
        $molt = 1 + self::recenti($playerId) * GameConfig::float('legge.recidiva_passo', 0.25);
        $sconto = 0.0;
        $sh = Database::first('SELECT ship_id FROM players WHERE id = ?', [$playerId]);
        if ($sh !== null && ($ship = PlayerService::ship((int) $sh['ship_id'])) !== null) {
            $sconto = min(80.0, (float) ($ship['mod_effects']['notoriety_reduce_pct'] ?? 0));
        }
        $aggiunti = round($base * $molt * (1 - $sconto / 100), 2);

        Database::run(
            'UPDATE players
                SET notorieta = notorieta * POW(0.5, TIMESTAMPDIFF(SECOND, COALESCE(notorieta_at, NOW()), NOW()) / 3600 / ?) + ?,
                    notorieta_at = NOW()
              WHERE id = ?',
            [max(1, GameConfig::int('legge.dimezza_ore', 24)), $aggiunti, $playerId]
        );
        Database::run('INSERT INTO crimini (player_id, kind, punti, sector_id) VALUES (?, ?, ?, ?)',
            [$playerId, $kind, $aggiunti, $sectorId]);

        $dopo = self::puntiDi($playerId);
        $g0 = self::grado($prima);
        $g1 = self::grado($dopo);
        if ($g1 > $g0) {
            $testo = 'La Federazione ti classifica ora come ' . mb_strtoupper(self::nome($g1)) . '. ' . self::RIASSUNTI[$g1];
            Live::alert($playerId, 'legge', 'Notorieta\': ' . self::nome($g1), $testo, '/gioco/fazioni');
            ShipLog::write($playerId, 'faction', $g1 >= 2 ? 'alert' : 'warning', 'Notorieta\': ' . self::nome($g1), $testo, $sectorId);
        }
        return ['punti' => $dopo, 'aggiunti' => $aggiunti, 'grado' => $g1, 'salito' => $g1 > $g0];
    }

    /** Taglia federale che oggi paga chi abbatte questo comandante. */
    public static function taglia(array $player): int
    {
        $p = isset($player['notorieta']) ? self::punti($player) : self::puntiDi((int) $player['id']);
        return self::grado($p) >= 2 ? (int) round($p * GameConfig::int('legge.taglia_per_punto', 300)) : 0;
    }

    /**
     * Notorieta' dopo aver pagato col proprio scafo (abbattuto per la taglia o
     * arrestato): la meta', e comunque sotto la soglia di Ricercato. Senza il
     * tetto un ricercato abbattuto restava ricercato, e un complice poteva
     * abbatterlo di nuovo e incassare ancora.
     */
    public static function dopoLaPena(float $punti): float
    {
        return round(min($punti / 2, self::soglie()[1] - 0.01), 2);
    }

    /**
     * Un comandante abbatte un ricercato: la Federazione paga la taglia e il
     * ricercato torna sotto la soglia (dopoLaPena). Lettura e riduzione nella
     * stessa istruzione vincolata: pagata una volta sola.
     */
    public static function riscuoti(int $vittimaId, int $cacciatoreId): int
    {
        $v = Database::first('SELECT id, notorieta, notorieta_at FROM players WHERE id = ?', [$vittimaId]);
        if ($v === null || ($taglia = self::taglia($v)) <= 0) {
            return 0;
        }
        if (Database::run(
            'UPDATE players SET notorieta = ?, notorieta_at = NOW() WHERE id = ? AND notorieta = ? AND notorieta_at <=> ?',
            [self::dopoLaPena(self::punti($v)), $vittimaId, $v['notorieta'], $v['notorieta_at']]
        )->rowCount() === 0) {
            return 0;
        }
        Wallet::credit($cacciatoreId, ['credits' => $taglia]);
        ShipLog::write($cacciatoreId, 'contract', 'info', 'Taglia federale su un ricercato',
            'La Federazione ha versato ' . number_format($taglia, 0, ',', '.') . ' cr per l\'abbattimento di un ricercato.');
        return $taglia;
    }

    /** Abbattuto da una pattuglia: torna sotto la soglia di Ricercato. */
    public static function arresto(int $playerId): void
    {
        Database::run('UPDATE players SET notorieta = ?, notorieta_at = NOW() WHERE id = ?',
            [self::dopoLaPena(self::puntiDi($playerId)), $playerId]);
    }

    /** Costo dell'ammenda che azzera la notorieta': cresce con la recidiva. */
    public static function costoAmmenda(int $playerId): int
    {
        $p = self::puntiDi($playerId);
        if ($p < self::soglie()[0]) {
            return 0;
        }
        $molt = 1 + self::recenti($playerId) * GameConfig::float('legge.recidiva_passo', 0.25);
        return (int) ceil($p * GameConfig::int('legge.ammenda_per_punto', 400) * $molt);
    }

    /** @param array<string,mixed> $player */
    public static function ammenda(array $player): array
    {
        $pid = (int) $player['id'];
        $costo = self::costoAmmenda($pid);
        if ($costo <= 0) {
            return ['ok' => false, 'error' => 'Non hai conti in sospeso con la Federazione.'];
        }
        $prima = Database::first('SELECT notorieta, notorieta_at FROM players WHERE id = ?', [$pid]);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge($pid, ['credits' => $costo])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Servono ' . number_format($costo, 0, ',', '.') . ' cr per l\'ammenda.'];
            }
            // vincolata alla notorieta' letta: un crimine commesso nel frattempo
            // cambia il conto, e l'ammenda va ricalcolata
            if (Database::run(
                'UPDATE players SET notorieta = 0, notorieta_at = NOW() WHERE id = ? AND notorieta = ? AND notorieta_at <=> ?',
                [$pid, $prima['notorieta'], $prima['notorieta_at']]
            )->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'La tua posizione e\' cambiata: ricarica la pagina e riprova.'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Database::run('DELETE FROM npcs WHERE kind = ? AND target_player_id = ?', ['patrol', $pid]);
        return ['ok' => true, 'cost' => $costo];
    }

    /** @return list<array<string,mixed>> */
    public static function fedina(int $playerId, int $limit = 12): array
    {
        return Database::all('SELECT kind, punti, sector_id, created_at FROM crimini WHERE player_id = ? ORDER BY id DESC LIMIT ?',
            [$playerId, $limit]);
    }

    // --- pattuglie --------------------------------------------------------------

    public static function rondeFinoA(): int
    {
        return max(0, min(Fasce::MAX, GameConfig::int('legge.ronde_fino_a', 3)));
    }

    /** Quante squadre d'intercettazione per questo gradino. */
    public static function squadre(int $grado): int
    {
        $v = array_map('intval', explode(',', GameConfig::str('legge.squadre', '0,0,1,2,3')));
        return max(0, $v[max(0, min(count($v) - 1, $grado))] ?? 0);
    }

    /** Forza di ogni squadra rispetto ai caccia del ricercato. */
    public static function forza(int $grado): float
    {
        $v = array_map('floatval', explode(',', GameConfig::str('legge.forza', '0,0,0.7,1.0,1.4')));
        return max(0.0, $v[max(0, min(count($v) - 1, $grado))] ?? 0.0);
    }

    /** @return array{spawned:int, hunting:int, dismissed:int} */
    public static function tick(): array
    {
        $out = ['spawned' => 0, 'hunting' => 0, 'dismissed' => 0];

        // ronde: pattuglie senza bersaglio nelle fasce vicine a Sol
        $ronde = (int) (Database::first("SELECT COUNT(*) n FROM npcs WHERE kind = 'patrol' AND target_player_id IS NULL")['n'] ?? 0);
        for ($i = $ronde; $i < GameConfig::int('legge.ronde', 14) && $i < $ronde + 4; $i++) {
            $sec = Database::first(
                'SELECT id, band FROM sectors WHERE is_fedspace = 0 AND band BETWEEN 1 AND ? ORDER BY RAND() LIMIT 1',
                [max(1, self::rondeFinoA())]
            );
            if ($sec === null) {
                break;
            }
            $b = (int) $sec['band'];
            [, $z] = Fasce::predoniCaccia(min(Fasce::MAX, $b + 1));
            self::nuovaPattuglia((int) $sec['id'], null, $b, (int) round($z * mt_rand(80, 120) / 100),
                Fasce::predoniRating($b) + 0.3);
            $out['spawned']++;
        }

        // squadre per i ricercati
        foreach (Database::all('SELECT id, sector_id, ship_id, notorieta, notorieta_at FROM players WHERE notorieta > 0') as $pl) {
            $g = self::grado(self::punti($pl));
            $voluti = self::squadre($g);
            $have = (int) (Database::first("SELECT COUNT(*) n FROM npcs WHERE kind = 'patrol' AND target_player_id = ? AND scade_at IS NULL", [(int) $pl['id']])['n'] ?? 0);
            $out['hunting'] += min($have, $voluti);
            if ($have > $voluti) {
                $out['dismissed'] += Database::run(
                    "DELETE FROM npcs WHERE kind = 'patrol' AND target_player_id = ? AND scade_at IS NULL ORDER BY id DESC LIMIT " . ($have - $voluti),
                    [(int) $pl['id']]
                )->rowCount();
                continue;
            }
            if ($have >= $voluti) {
                continue;
            }
            // Tutte le squadre del gradino partono insieme, ognuna da un settore
            // a due o tre salti, tarate sulla nave del ricercato.
            $ship = PlayerService::ship((int) $pl['ship_id']);
            $ftr = max(2000, (int) round((int) ($ship['fighters'] ?? 0) * self::forza($g)));
            for ($i = $have; $i < $voluti; $i++) {
                $vicino = self::settoreDiPartenza((int) $pl['sector_id']);
                if ($vicino === null) {
                    break;
                }
                self::nuovaPattuglia($vicino, (int) $pl['id'], Fasce::diSettore($vicino), $ftr,
                    1.5 + 0.2 * ($g - 2) + mt_rand(0, 20) / 100, max(500, (int) round((int) ($ship['shields'] ?? 0) * self::forza($g))));
                $out['spawned']++;
                $out['hunting']++;
            }
            Live::alert((int) $pl['id'], 'legge', 'Squadre d\'intercettazione',
                ($voluti - $have === 1 ? 'Una squadra federale e\' sulle tue tracce' : ($voluti - $have) . ' squadre federali sono sulle tue tracce')
                . ', a pochi salti dal settore ' . (int) $pl['sector_id'] . '.', '/gioco/fazioni');
        }

        // squadre rimaste senza un ricercato da inseguire
        foreach (Database::all("SELECT DISTINCT n.target_player_id pid FROM npcs n WHERE n.kind = 'patrol' AND n.target_player_id IS NOT NULL AND n.scade_at IS NULL") as $r) {
            $p = Database::first('SELECT id, notorieta, notorieta_at FROM players WHERE id = ?', [(int) $r['pid']]);
            if ($p === null || self::grado(self::punti($p)) < 2) {
                $out['dismissed'] += Database::run("DELETE FROM npcs WHERE kind = 'patrol' AND target_player_id = ? AND scade_at IS NULL", [(int) $r['pid']])->rowCount();
            }
        }
        // pattuglie di soccorso a fine turno di servizio
        $out['dismissed'] += Database::run("DELETE FROM npcs WHERE kind = 'patrol' AND scade_at IS NOT NULL AND scade_at < NOW()")->rowCount();
        return $out;
    }

    /**
     * Un mercantile sotto attacco chiama soccorso: con la probabilita' della
     * fascia, una pattuglia parte a due o tre salti e insegue l'aggressore,
     * ricercato o no, per mercanti.soccorso_min minuti. Una seconda chiamata
     * mentre la pattuglia e' in viaggio ne prolunga il servizio.
     */
    public static function chiamaSoccorso(int $aggressoreId, int $sectorId): bool
    {
        $band = Fasce::diSettore($sectorId);
        if (mt_rand(1, 100) > Fasce::soccorsoPct($band)) {
            return false;
        }
        $min = max(1, GameConfig::int('mercanti.soccorso_min', 30));
        if (Database::run(
            "UPDATE npcs SET scade_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE kind = 'patrol' AND target_player_id = ? AND scade_at IS NOT NULL",
            [$min, $aggressoreId]
        )->rowCount() > 0) {
            return true;
        }
        $dove = self::settoreDiPartenza($sectorId);
        if ($dove === null) {
            return false;
        }
        $sh = Database::first('SELECT ship_id FROM players WHERE id = ?', [$aggressoreId]);
        $ship = $sh !== null ? PlayerService::ship((int) $sh['ship_id']) : null;
        [, $z] = Fasce::predoniCaccia(min(Fasce::MAX, max(1, $band) + 1));
        $ftr = max($z, (int) round((int) ($ship['fighters'] ?? 0) * GameConfig::float('mercanti.soccorso_forza', 0.6)));
        self::nuovaPattuglia($dove, $aggressoreId, Fasce::diSettore($dove), $ftr, 1.5 + mt_rand(0, 30) / 100, null, $min);
        return true;
    }

    /** Un settore a due o tre salti dal ricercato, fuori dalla Federazione se si puo'. */
    private static function settoreDiPartenza(int $da): ?int
    {
        $adj = Universe::adjacency();
        $dist = [$da => 0];
        $coda = [$da];
        $anello = [];
        while ($coda !== []) {
            $s = array_shift($coda);
            if ($dist[$s] >= 3) {
                continue;
            }
            foreach ($adj[$s] ?? [] as $t) {
                if (!isset($dist[$t])) {
                    $dist[$t] = $dist[$s] + 1;
                    $coda[] = $t;
                    if ($dist[$t] >= 2) {
                        $anello[] = $t;
                    }
                }
            }
        }
        $fuori = array_values(array_filter($anello, static fn ($s) => Fasce::diSettore($s) > 0));
        $scelta = $fuori !== [] ? $fuori : ($anello !== [] ? $anello : array_keys($dist));
        return $scelta === [] ? null : (int) $scelta[array_rand($scelta)];
    }

    private static function nuovaPattuglia(int $sectorId, ?int $target, int $band, int $fighters, float $rating, ?int $shields = null, ?int $minuti = null): void
    {
        Database::run(
            "INSERT INTO npcs (kind, name, ship_type, sector_id, home_sector, band, target_player_id, scade_at, fighters, shields, combat_rating, credits, aggression)
             VALUES ('patrol', ?, ?, ?, ?, ?, ?, IF(? IS NULL, NULL, DATE_ADD(NOW(), INTERVAL ? MINUTE)), ?, ?, ?, ?, 0)",
            [
                $target === null ? self::PATTUGLIA : ($minuti !== null ? self::SOCCORSO : self::SQUADRA),
                $target === null ? 'missile_frigate' : 'havoc_gunstar',
                $sectorId, $sectorId, $band, $target, $minuti, $minuti, $fighters,
                $shields ?? (int) round($fighters * 0.25), round($rating, 2),
                mt_rand(1000, 5000) * max(1, $band),
            ]
        );
    }
}
