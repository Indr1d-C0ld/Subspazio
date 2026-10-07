<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Consumabili: oggetti monouso trovati come bottino. Si accumulano nella
 * stiva consumabili del comandante e si usano dalla plancia, uno alla volta.
 * Quelli che agiscono «al prossimo» evento passano per gli effetti in attesa
 * dell'equipaggio (Crew), gli stessi delle abilita' degli ufficiali.
 */
final class Consumabili
{
    /** chiave => [nome, rarita', descrizione] */
    public const CATALOGO = [
        'nanoriparatore'      => ['Nanoriparatori', 'civ', 'Riportano gli scudi al massimo.'],
        'container_caccia'    => ['Container di caccia', 'civ', 'Un decimo del tetto di caccia della nave (almeno 200), fino al tetto.'],
        'carica_emp'          => ['Carica EMP', 'mil', 'Al prossimo ingresso in un settore ostile nessuno ti aggancia (entro 2 ore).'],
        'acceleratore'        => ['Acceleratore di warp', 'mil', 'Il prossimo salto non costa turni (entro 2 ore).'],
        'esca'                => ['Esca olografica', 'exp', 'Per 30 minuti gli ostili inseguono un fantasma: nessuno ti aggancia da fermo.'],
        'nucleo_sovraccarico' => ['Nucleo in sovraccarico', 'exp', 'Il prossimo attacco colpisce il 25% più forte.'],
        'codice_amnistia'     => ['Codice di amnistia contraffatto', 'xeno', 'Cancella 60 punti di notorietà.'],
        'sonda_fortuna'       => ['Sonda di recupero Precursore', 'precursor', 'Il prossimo nemico abbattuto lascia di sicuro un modulo.'],
    ];

    /** @return list<array{ckey:string, nome:string, rarita:string, descr:string, qty:int}> */
    public static function inventario(int $playerId): array
    {
        $out = [];
        foreach (Database::all('SELECT ckey, qty FROM player_consumabili WHERE player_id = ? AND qty > 0', [$playerId]) as $r) {
            if (isset(self::CATALOGO[$r['ckey']])) {
                [$nome, $rar, $descr] = self::CATALOGO[$r['ckey']];
                $out[] = ['ckey' => $r['ckey'], 'nome' => $nome, 'rarita' => $rar, 'descr' => $descr, 'qty' => (int) $r['qty']];
            }
        }
        usort($out, static fn ($a, $b) => array_search($a['rarita'], Loot::RARITIES, true) <=> array_search($b['rarita'], Loot::RARITIES, true));
        return $out;
    }

    public static function aggiungi(int $playerId, string $ckey, int $qty = 1): void
    {
        if ($qty > 0 && isset(self::CATALOGO[$ckey])) {
            Database::run(
                'INSERT INTO player_consumabili (player_id, ckey, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
                [$playerId, $ckey, $qty]
            );
        }
    }

    /**
     * Forse un consumabile dal bottino: loot.consumabile_pct per la
     * probabilita' di bottino della fascia; la rarita' segue i pesi della
     * fascia, come per i moduli.
     *
     * @return array{key:string, name:string, rarity:string}|null
     */
    public static function tira(int $playerId, int $band): ?array
    {
        if (mt_rand() / mt_getrandmax() >= min(0.9, GameConfig::float('loot.consumabile_pct', 0.25) * Fasce::bottinoMult($band))) {
            return null;
        }
        $pesi = Fasce::pesiRarita(max(1, $band));
        $scelte = [];
        $tot = 0;
        foreach (self::CATALOGO as $k => [$nome, $rar]) {
            $w = (int) ($pesi[$rar] ?? 0);
            if ($w > 0) {
                $scelte[] = [$k, $w];
                $tot += $w;
            }
        }
        if ($tot <= 0) {
            return null;
        }
        $roll = mt_rand(1, $tot);
        foreach ($scelte as [$k, $w]) {
            $roll -= $w;
            if ($roll <= 0) {
                self::aggiungi($playerId, $k);
                return ['key' => $k, 'name' => self::CATALOGO[$k][0], 'rarity' => self::CATALOGO[$k][1]];
            }
        }
        return null;
    }

    /** Un'esca olografica e' attiva? (non si consuma: dura 30 minuti) */
    public static function escaAttiva(int $playerId): bool
    {
        return Database::first(
            "SELECT 1 x FROM crew_pending WHERE player_id = ? AND effect = 'esca' AND expires_at > NOW() LIMIT 1",
            [$playerId]
        ) !== null;
    }

    /** @param array<string,mixed> $player */
    public static function usa(array $player, string $ckey): array
    {
        $pid = (int) $player['id'];
        if (!isset(self::CATALOGO[$ckey])) {
            return ['ok' => false, 'error' => 'Oggetto sconosciuto.'];
        }
        $nome = self::CATALOGO[$ckey][0];
        $ship = PlayerService::ship((int) $player['ship_id']);
        if ($ship === null || $ship['type_key'] === 'escape_pod') {
            return ['ok' => false, 'error' => 'Dalla capsula di salvataggio non puoi usare nulla.'];
        }
        // utili solo se servono: non si sprecano
        if ($ckey === 'nanoriparatore' && (int) $ship['shields'] >= (int) $ship['max_shields']) {
            return ['ok' => false, 'error' => 'Gli scudi sono già al massimo.'];
        }
        if ($ckey === 'container_caccia' && (int) $ship['fighters'] >= (int) $ship['max_fighters']) {
            return ['ok' => false, 'error' => 'I caccia sono già al tetto della nave.'];
        }
        if ($ckey === 'codice_amnistia' && Legge::puntiDi($pid) <= 0) {
            return ['ok' => false, 'error' => 'Non hai notorietà da cancellare.'];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (Database::run('UPDATE player_consumabili SET qty = qty - 1 WHERE player_id = ? AND ckey = ? AND qty > 0', [$pid, $ckey])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Non hai {$nome}."];
            }
            $fra = static fn (int $min): string => date('Y-m-d H:i:s', time() + $min * 60);
            $msg = match ($ckey) {
                'nanoriparatore' => (function () use ($ship): string {
                    Database::run('UPDATE ships SET shields = GREATEST(shields, ?) WHERE id = ?', [(int) $ship['max_shields'], (int) $ship['id']]);
                    return 'Scudi al massimo: ' . number_format((int) $ship['max_shields'], 0, ',', '.') . '.';
                })(),
                'container_caccia' => (function () use ($ship): string {
                    $q = max(200, intdiv((int) $ship['max_fighters'], 10));
                    Database::run('UPDATE ships SET fighters = LEAST(?, fighters + ?) WHERE id = ?', [(int) $ship['max_fighters'], $q, (int) $ship['id']]);
                    return 'Caccia imbarcati: fino a +' . number_format($q, 0, ',', '.') . '.';
                })(),
                'carica_emp' => (function () use ($pid, $fra): string {
                    Crew::addPending($pid, 'no_engage', 1, $fra(120));
                    return 'Carica EMP pronta: al prossimo ingresso ostile nessuno ti aggancia.';
                })(),
                'acceleratore' => (function () use ($pid, $fra): string {
                    Crew::addPending($pid, 'free_warp', 1, $fra(120));
                    return 'Acceleratore inserito: il prossimo salto non costa turni.';
                })(),
                'esca' => (function () use ($pid, $fra): string {
                    Crew::addPending($pid, 'esca', 1, $fra(30));
                    return 'Esca lanciata: per 30 minuti gli ostili inseguono un fantasma.';
                })(),
                'nucleo_sovraccarico' => (function () use ($pid, $fra): string {
                    Crew::addPending($pid, 'attack_bonus_pct', 25, $fra(120));
                    return 'Nucleo in sovraccarico: il prossimo attacco colpisce il 25% più forte.';
                })(),
                'codice_amnistia' => (function () use ($pid): string {
                    $p = Legge::puntiDi($pid);
                    Database::run('UPDATE players SET notorieta = ?, notorieta_at = NOW() WHERE id = ?', [max(0, round($p - 60, 2)), $pid]);
                    return 'Il fascicolo federale perde ' . number_format(min(60, $p), 1, ',', '.') . ' punti di notorietà.';
                })(),
                'sonda_fortuna' => (function () use ($pid, $fra): string {
                    Crew::addPending($pid, 'guaranteed_drop', 1, $fra(240));
                    return 'Sonda armata: il prossimo nemico abbattuto lascerà un modulo.';
                })(),
            };
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => $nome, 'msg' => $msg];
    }
}
