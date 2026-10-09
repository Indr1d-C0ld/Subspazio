<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Reperti e progetti: il bottino che non si monta sulla nave.
 *
 * I reperti si rivendono allo StarDock o si tengono per completare una
 * collezione (cinque pezzi), che paga una volta sola crediti, esperienza e un
 * modulo. I progetti sbloccano le ricette d'Officina dei moduli Xeno e
 * Precursore che non si possono produrre altrimenti.
 */
final class Reperti
{
    /** collezione => [nome, crediti, esperienza, rarita' minima del modulo in premio] */
    public const COLLEZIONI = [
        'federazione' => ['Archivio della Prima Federazione', 25000, 500, 'mil'],
        'ferrengi'    => ['Cimeli del Consorzio Ferrengi', 80000, 1500, 'exp'],
        'precursori'  => ['Reliquie dei Precursori', 250000, 4000, 'precursor'],
    ];

    /** reperto => [nome, rarita', valore allo StarDock, collezione] */
    public const CATALOGO = [
        'medaglia_ammiraglio' => ['Medaglia del Primo Ammiraglio', 'civ', 1500, 'federazione'],
        'registro_pioniera'   => ['Registro di bordo della Pioniera', 'civ', 1800, 'federazione'],
        'bandiera_sol'        => ['Bandiera di Sol bruciata', 'mil', 3000, 'federazione'],
        'chiave_federale'     => ['Chiave cifrata federale', 'mil', 3500, 'federazione'],
        'mappa_pergamena'     => ['Mappa stellare su pergamena', 'mil', 4000, 'federazione'],
        'regola_34'           => ['Regola d\'Acquisizione n. 34, incisa', 'exp', 9000, 'ferrengi'],
        'bilancia_nagus'      => ['Bilancia d\'oro del Nagus', 'exp', 11000, 'ferrengi'],
        'orecchio_bronzo'     => ['Orecchio di bronzo votivo', 'exp', 10000, 'ferrengi'],
        'scrigno_latinum'     => ['Scrigno di latinum (vuoto)', 'xeno', 18000, 'ferrengi'],
        'contratto_truffa'    => ['Contratto truffaldino firmato', 'xeno', 20000, 'ferrengi'],
        'frammento_monolite'  => ['Frammento di monolite', 'xeno', 35000, 'precursori'],
        'cristallo_cantante'  => ['Cristallo cantante', 'xeno', 40000, 'precursori'],
        'sfera_navigazione'   => ['Sfera di navigazione', 'precursor', 70000, 'precursori'],
        'glifo_luminoso'      => ['Glifo luminoso', 'precursor', 80000, 'precursori'],
        'seme_stella'         => ['Seme di stella', 'precursor', 120000, 'precursori'],
    ];

    // --- reperti --------------------------------------------------------------

    /** @return array<string,int> reperto => quantita' */
    public static function posseduti(int $playerId): array
    {
        $out = [];
        foreach (Database::all('SELECT ckey, qty FROM player_reperti WHERE player_id = ? AND qty > 0', [$playerId]) as $r) {
            $out[(string) $r['ckey']] = (int) $r['qty'];
        }
        return $out;
    }

    /** @return list<string> collezioni gia' completate */
    public static function completate(int $playerId): array
    {
        return array_column(Database::all('SELECT collezione FROM player_collezioni WHERE player_id = ?', [$playerId]), 'collezione');
    }

    public static function aggiungi(int $playerId, string $ckey, int $qty = 1): void
    {
        if ($qty > 0 && isset(self::CATALOGO[$ckey])) {
            Database::run(
                'INSERT INTO player_reperti (player_id, ckey, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)',
                [$playerId, $ckey, $qty]
            );
        }
    }

    /**
     * Forse un reperto: loot.reperto_pct per la probabilita' di bottino della
     * fascia (moltiplicata per $molt, es. nei relitti), rarita' dai pesi della
     * fascia.
     *
     * @return array{key:string, name:string, rarity:string}|null
     */
    public static function tira(int $playerId, int $band, float $molt = 1.0): ?array
    {
        if (mt_rand() / mt_getrandmax() >= min(0.9, GameConfig::float('loot.reperto_pct', 0.12) * Fasce::bottinoMult($band) * $molt)) {
            return null;
        }
        $k = self::sceltaPesata(array_map(static fn ($r) => $r[1], self::CATALOGO), Fasce::pesiRarita(max(1, $band)));
        if ($k === null) {
            return null;
        }
        self::aggiungi($playerId, $k);
        Stats::add($playerId, 'reperti_trovati');
        return ['key' => $k, 'name' => self::CATALOGO[$k][0], 'rarity' => self::CATALOGO[$k][1]];
    }

    /** Vende reperti allo StarDock al loro valore. @param array<string,mixed> $player */
    public static function vendi(array $player, string $ckey, int $qty): array
    {
        if (!Shipyard::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'I reperti si vendono all\'antiquario dello StarDock.'];
        }
        if (!isset(self::CATALOGO[$ckey]) || $qty <= 0) {
            return ['ok' => false, 'error' => 'Reperto sconosciuto.'];
        }
        $pid = (int) $player['id'];
        $valore = self::CATALOGO[$ckey][2] * $qty;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (Database::run('UPDATE player_reperti SET qty = qty - ? WHERE player_id = ? AND ckey = ? AND qty >= ?', [$qty, $pid, $ckey, $qty])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Non ne hai abbastanza.'];
            }
            Wallet::credit($pid, ['credits' => $valore]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'name' => self::CATALOGO[$ckey][0], 'qty' => $qty, 'credits' => $valore];
    }

    /**
     * Completa una collezione: consuma un esemplare di ciascuno dei cinque
     * reperti e paga, una volta sola, crediti, esperienza e un modulo.
     *
     * @param array<string,mixed> $player
     */
    public static function completa(array $player, string $collezione): array
    {
        if (!isset(self::COLLEZIONI[$collezione])) {
            return ['ok' => false, 'error' => 'Collezione sconosciuta.'];
        }
        $pid = (int) $player['id'];
        [$nome, $crediti, $xp, $rarita] = self::COLLEZIONI[$collezione];
        $pezzi = array_keys(array_filter(self::CATALOGO, static fn ($r) => $r[3] === $collezione));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // la chiave primaria rende la ricompensa unica anche con due click insieme
            if (Database::run('INSERT IGNORE INTO player_collezioni (player_id, collezione) VALUES (?, ?)', [$pid, $collezione])->rowCount() === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Hai già completato l'{$nome}."];
            }
            foreach ($pezzi as $k) {
                if (Database::run('UPDATE player_reperti SET qty = qty - 1 WHERE player_id = ? AND ckey = ? AND qty >= 1', [$pid, $k])->rowCount() === 0) {
                    $pdo->rollBack();
                    return ['ok' => false, 'error' => 'Ti manca ' . self::CATALOGO[$k][0] . '.'];
                }
            }
            Database::run('UPDATE players SET credits = credits + ?, experience = experience + ? WHERE id = ?', [$crediti, $xp, $pid]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $modulo = Loot::grant($pid, 'collezione', true, $rarita);
        Stats::add($pid, 'collezioni');
        // «tutte e tre» vuol dire insieme, non in tre stagioni diverse
        Stats::max($pid, 'collezioni_insieme', (int) (Database::first(
            'SELECT COUNT(*) n FROM player_collezioni WHERE player_id = ?', [$pid])['n'] ?? 0));
        return ['ok' => true, 'name' => $nome, 'credits' => $crediti, 'xp' => $xp, 'modulo' => $modulo];
    }

    // --- progetti -----------------------------------------------------------

    /** @return list<string> ricette sbloccate da un progetto */
    public static function progetti(int $playerId): array
    {
        return array_column(Database::all('SELECT recipe_key FROM player_progetti WHERE player_id = ?', [$playerId]), 'recipe_key');
    }

    public static function haProgetto(int $playerId, string $recipeKey): bool
    {
        return Database::first('SELECT 1 x FROM player_progetti WHERE player_id = ? AND recipe_key = ?', [$playerId, $recipeKey]) !== null;
    }

    /**
     * Forse un progetto: solo dalla Frontiera in fuori, loot.progetto_pct per
     * la probabilita' di bottino della fascia; $certo per i comandanti
     * d'elite. Fra i progetti che il comandante non ha, pesati sulla rarita'
     * del modulo secondo la fascia.
     *
     * @return array{key:string, name:string, rarity:string}|null
     */
    public static function tiraProgetto(int $playerId, int $band, bool $certo = false): ?array
    {
        if ($band < 3 || (!$certo && mt_rand() / mt_getrandmax() >= GameConfig::float('loot.progetto_pct', 0.03) * Fasce::bottinoMult($band))) {
            return null;
        }
        $mancanti = Database::all(
            'SELECT r.ckey, r.label, it.rarity FROM recipes r JOIN item_types it ON it.ckey = r.output_item
              WHERE r.progetto = 1 AND NOT EXISTS (SELECT 1 FROM player_progetti pp WHERE pp.player_id = ? AND pp.recipe_key = r.ckey)',
            [$playerId]
        );
        if ($mancanti === []) {
            return null;
        }
        $perChiave = [];
        foreach ($mancanti as $m) {
            $perChiave[(string) $m['ckey']] = (string) $m['rarity'];
        }
        $k = self::sceltaPesata($perChiave, Fasce::pesiRarita($band)) ?? array_key_first($perChiave);
        if (Database::run('INSERT IGNORE INTO player_progetti (player_id, recipe_key) VALUES (?, ?)', [$playerId, $k])->rowCount() === 0) {
            return null;
        }
        Stats::add($playerId, 'progetti');
        Stats::max($playerId, 'progetti_insieme', (int) (Database::first(
            'SELECT COUNT(*) n FROM player_progetti WHERE player_id = ?', [$playerId])['n'] ?? 0));
        $m = array_values(array_filter($mancanti, static fn ($x) => $x['ckey'] === $k))[0];
        return ['key' => $k, 'name' => 'Progetto: ' . $m['label'], 'rarity' => $m['rarity']];
    }

    /**
     * Sceglie una chiave pesando la sua rarita' coi pesi dati.
     *
     * @param array<string,string> $rarita chiave => rarita'
     * @param array<string,int>    $pesi   rarita' => peso
     */
    private static function sceltaPesata(array $rarita, array $pesi): ?string
    {
        $tot = 0;
        foreach ($rarita as $r) {
            $tot += max(0, (int) ($pesi[$r] ?? 0));
        }
        if ($tot <= 0) {
            return null;
        }
        $roll = mt_rand(1, $tot);
        foreach ($rarita as $k => $r) {
            $roll -= max(0, (int) ($pesi[$r] ?? 0));
            if ($roll <= 0) {
                return (string) $k;
            }
        }
        return null;
    }
}
