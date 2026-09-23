<?php

declare(strict_types=1);

namespace App\Game;

use App\Core\Database;

/**
 * Cantiere dello StarDock: acquisto navi (con permuta), potenziamenti
 * (stive / caccia / scudi) e hardware (sonde, mine, capsula, scanner,
 * transwarp, occultamento).
 */
final class Shipyard
{
    private const HARDWARE = [
        'probe'            => ['col' => 'probes',       'price' => 'hardware.probe_price',           'cap' => 'hardware.probe_capacity'],
        'genesis'          => ['col' => 'genesis',      'price' => 'hardware.genesis_price',         'cap' => 'hardware.genesis_capacity'],
        'armid'            => ['col' => 'mines_armid',  'price' => 'hardware.armid_price',           'cap' => 'hardware.mine_capacity'],
        'limpet'           => ['col' => 'mines_limpet', 'price' => 'hardware.limpet_price',          'cap' => 'hardware.mine_capacity'],
        'escape_pod'       => ['col' => 'escape_pod',   'price' => 'hardware.escape_pod_price',      'flag' => true],
        'mining_laser'     => ['col' => 'mining_laser', 'price' => 'hardware.mining_laser_price',    'flag' => true],
        'scanner_density'  => ['col' => 'dev_scanner',  'price' => 'hardware.scanner_density_price', 'enum' => 'density'],
        'scanner_holo'     => ['col' => 'dev_scanner',  'price' => 'hardware.scanner_holo_price',    'enum' => 'holo'],
        'transwarp'        => ['col' => 'dev_transwarp', 'price' => 'hardware.transwarp_price',      'flag' => true],
        'cloak'            => ['col' => 'dev_cloak',    'price' => 'hardware.cloak_price',           'flag' => true],
    ];

    public static function atShipyard(int $sectorId): bool
    {
        return Database::first('SELECT 1 AS x FROM sectors WHERE id = ? AND is_stardock = 1', [$sectorId]) !== null;
    }

    /** @return list<array<string,mixed>> */
    public static function catalog(): array
    {
        return Database::all("SELECT * FROM ship_types WHERE ckey <> 'escape_pod' ORDER BY sort_order");
    }

    /** @param array<string,mixed> $ship */
    public static function tradeInValue(array $ship): int
    {
        $type = Database::first('SELECT base_cost FROM ship_types WHERE ckey = ?', [$ship['type_key']]);
        return (int) floor((int) ($type['base_cost'] ?? 0) * 0.4);
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function buyShip(array $player, array $ship, string $typeKey): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        // Il bando federale sta nel motore, non solo nel controller: prima le
        // pagine lo applicavano e le rotte /api no, e il bando si aggirava.
        if (($ship['type_key'] ?? '') !== 'escape_pod' && ($bando = Faction::stardockBlocked((int) $player['id']))) {
            return ['ok' => false, 'error' => $bando];
        }
        $type = Database::first("SELECT * FROM ship_types WHERE ckey = ? AND ckey <> 'escape_pod'", [$typeKey]);
        if ($type === null) {
            return ['ok' => false, 'error' => 'Modello sconosciuto.'];
        }
        if ($type['ckey'] === $ship['type_key']) {
            return ['ok' => false, 'error' => 'Hai gia\' questo modello.'];
        }

        $tradeIn = self::tradeInValue($ship);
        $cost = max(0, (int) $type['base_cost'] - $tradeIn);
        if ((int) $player['credits'] < $cost) {
            return ['ok' => false, 'error' => "Servono {$cost} cr (permuta {$tradeIn}); ne hai " . (int) $player['credits'] . '.'];
        }

        $cargo = Economy::holdsUsed($ship);
        if ($cargo > (int) $type['base_holds']) {
            return ['ok' => false, 'error' => "Svuota le stive: la nuova nave ha {$type['base_holds']} stive, ne usi {$cargo}."];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge((int) $player['id'], ['credits' => $cost])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Servono {$cost} cr (permuta {$tradeIn})."];
            }
            // caccia/scudi/scanner/transwarp/cloak NON si trasferiscono; sonde/mine/genesis si'.
            Database::run(
                "UPDATE ships SET type_key = ?, name = ?, holds_total = ?, fighters = ?, shields = ?,
                 dev_scanner = 'none', dev_transwarp = 0, dev_cloak = 0, cloaked = 0
                 WHERE id = ?",
                [
                    $type['ckey'],
                    'SS ' . $player['handle'],
                    (int) $type['base_holds'],
                    (int) $type['base_fighters'],
                    (int) $type['base_shields'],
                    $ship['id'],
                ]
            );
            Database::run('DELETE FROM ship_limpets WHERE ship_id = ?', [$ship['id']]);
            self::unshipModules((int) $ship['id'], (int) $player['id']);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Crew::adattaAlloScafo((int) $player['id']);
        return ['ok' => true, 'cost' => $cost, 'trade_in' => $tradeIn, 'type' => $type['ckey'], 'name' => $type['name']];
    }

    /**
     * Nave di soccorso della Federazione: uno scafo base gratuito quando si e'
     * in capsula e senza crediti per comprarne uno. Evita che la distruzione
     * della nave sia un vicolo cieco.
     *
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function rescueShip(array $player, array $ship): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'La nave di soccorso si ritira solo allo StarDock.'];
        }
        if (($ship['type_key'] ?? '') !== 'escape_pod') {
            return ['ok' => false, 'error' => 'Hai gia\' una nave: usa il catalogo del cantiere.'];
        }

        $wanted = GameConfig::str('hardware.rescue_ship_type', 'scout_marauder');
        $type = Database::first("SELECT * FROM ship_types WHERE ckey = ? AND ckey <> 'escape_pod'", [$wanted])
            ?? Database::first("SELECT * FROM ship_types WHERE ckey <> 'escape_pod' ORDER BY base_cost ASC, sort_order ASC LIMIT 1");
        if ($type === null) {
            return ['ok' => false, 'error' => 'Nessun modello disponibile.'];
        }
        // "Al verde" conta anche la banca, che sta nello stesso StarDock: prima
        // bastava versare tutto, farsi dare lo scafo gratis e ritirare.
        $inBanca = (int) (Database::first('SELECT balance FROM bank_accounts WHERE player_id = ?', [(int) $player['id']])['balance'] ?? 0);
        if ((int) $player['credits'] + $inBanca >= (int) $type['base_cost']) {
            return ['ok' => false, 'error' => 'Puoi permetterti una nave dal catalogo: la nave di soccorso e\' solo per chi e\' a secco.'];
        }

        Database::run(
            "UPDATE ships SET type_key = ?, name = ?, holds_total = ?, fighters = ?, shields = ?,
             hold_ore = 0, hold_organics = 0, hold_equipment = 0, hold_colonists = 0,
             dev_scanner = 'none', dev_transwarp = 0, dev_cloak = 0, cloaked = 0
             WHERE id = ?",
            [
                $type['ckey'],
                'SS ' . $player['handle'],
                (int) $type['base_holds'],
                (int) $type['base_fighters'],
                (int) $type['base_shields'],
                $ship['id'],
            ]
        );
        Database::run('DELETE FROM ship_limpets WHERE ship_id = ?', [$ship['id']]);
        self::unshipModules((int) $ship['id'], (int) $player['id']);

        Crew::adattaAlloScafo((int) $player['id']);
        return ['ok' => true, 'type' => $type['ckey'], 'name' => $type['name']];
    }

    /** Al cambio scafo i moduli si disinstallano: tornano in inventario (o si perdono). */
    private static function unshipModules(int $shipId, int $playerId): void
    {
        try {
            if (GameConfig::int('loot.keep_modules_on_refit', 1) === 1) {
                foreach (Database::all('SELECT item_key, rolled, broken_at FROM ship_modules WHERE ship_id = ?', [$shipId]) as $m) {
                    // il guasto resta: cambiare nave non e' una riparazione
                    Database::run(
                        'INSERT INTO player_items (player_id, item_key, rolled, broken_at, source) VALUES (?, ?, ?, ?, ?)',
                        [$playerId, $m['item_key'], $m['rolled'], $m['broken_at'], 'shop']
                    );
                }
            }
            Database::run('DELETE FROM ship_modules WHERE ship_id = ?', [$shipId]);
        } catch (\Throwable) {
            // tabelle non ancora migrate
        }
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     * @param 'holds'|'fighters'|'shields' $kind
     */
    public static function upgrade(array $player, array $ship, string $kind, int $qty): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        if (($ship['type_key'] ?? '') !== 'escape_pod' && ($bando = Faction::stardockBlocked((int) $player['id']))) {
            return ['ok' => false, 'error' => $bando];
        }
        if ($qty <= 0) {
            return ['ok' => false, 'error' => 'Quantita\' non valida.'];
        }
        $type = Database::first('SELECT * FROM ship_types WHERE ckey = ?', [$ship['type_key']]);

        [$col, $max, $unit] = match ($kind) {
            'holds' => ['holds_total', (int) $type['max_holds'],
                (int) round((int) $type['hold_price'] * GameConfig::float('hardware.hold_price_mult', 1.0))],
            'fighters' => ['fighters', (int) $type['max_fighters'], (int) ceil(GameConfig::float('hardware.fighter_price', 12))],
            'shields' => ['shields', (int) $type['max_shields'], (int) ceil(GameConfig::float('hardware.shield_price', 8))],
            default => [null, 0, 0],
        };
        if ($col === null) {
            return ['ok' => false, 'error' => 'Potenziamento sconosciuto.'];
        }

        // $ship e' la nave EFFETTIVA (moduli, EPS): per le stive e i caccia conta
        // quel che si e' comprato davvero, cioe' la riga grezza; per gli scudi il
        // tetto e' quello effettivo — un modulo che lo alza va potuto riempire,
        // una griglia che lo abbassa non va aggirata comprando scudi.
        $grezza = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        if ($kind === 'shields') {
            $max = (int) ($ship['max_shields'] ?? $max);
        }
        $room = $max - (int) $grezza[$col];
        if ($room <= 0) {
            return ['ok' => false, 'error' => 'Gia\' al massimo per questo scafo.'];
        }
        $qty = min($qty, $room);
        $cost = $qty * $unit;
        if ((int) $player['credits'] < $cost) {
            $aff = $unit > 0 ? intdiv((int) $player['credits'], $unit) : 0;
            return ['ok' => false, 'error' => "Servono {$cost} cr; puoi permetterti {$aff} unita\'."];
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge((int) $player['id'], ['credits' => $cost])) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => "Servono {$cost} cr."];
            }
            // il tetto sta anche nella WHERE: due acquisti paralleli non lo superano
            $consegnato = Database::run(
                "UPDATE ships SET {$col} = {$col} + ? WHERE id = ? AND {$col} + ? <= ?",
                [$qty, $ship['id'], $qty, $max]
            )->rowCount() > 0;
            if (!$consegnato) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Gia\' al massimo per questo scafo.'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['ok' => true, 'kind' => $kind, 'qty' => $qty, 'cost' => $cost];
    }

    /**
     * @param array<string,mixed> $player
     * @param array<string,mixed> $ship
     */
    public static function buyHardware(array $player, array $ship, string $item, int $qty = 1): array
    {
        if (!self::atShipyard((int) $player['sector_id'])) {
            return ['ok' => false, 'error' => 'Il cantiere e\' solo allo StarDock.'];
        }
        if (($ship['type_key'] ?? '') !== 'escape_pod' && ($bando = Faction::stardockBlocked((int) $player['id']))) {
            return ['ok' => false, 'error' => $bando];
        }
        $spec = self::HARDWARE[$item] ?? null;
        if ($spec === null) {
            return ['ok' => false, 'error' => 'Articolo sconosciuto.'];
        }
        $unit = GameConfig::int($spec['price'], 0);
        // Cosa c'e' davvero installato lo dice la riga grezza: quella effettiva
        // puo' mostrare uno scanner alzato o abbassato da moduli o EPS, e allora
        // si pagava un olografico che c'era gia', o se ne rifiutava uno che non c'era.
        $grezza = Database::first('SELECT * FROM ships WHERE id = ?', [(int) $ship['id']]);
        $col = $spec['col'];

        if (!empty($spec['flag'])) {
            if ((int) $grezza[$col] === 1) {
                return ['ok' => false, 'error' => 'Gia\' installato.'];
            }
            if ((int) $player['credits'] < $unit) {
                return ['ok' => false, 'error' => "Servono {$unit} cr."];
            }
            $esito = self::payAndApply((int) $player['id'], $unit, "UPDATE ships SET {$col} = 1 WHERE id = ? AND {$col} = 0", [$ship['id']]);
            return $esito === 'ok' ? ['ok' => true, 'item' => $item, 'cost' => $unit]
                : ['ok' => false, 'error' => $esito === 'crediti' ? "Servono {$unit} cr." : 'Gia\' installato.'];
        }

        if (!empty($spec['enum'])) {
            if ($grezza[$col] === $spec['enum']) {
                return ['ok' => false, 'error' => 'Gia\' installato.'];
            }
            if ((int) $player['credits'] < $unit) {
                return ['ok' => false, 'error' => "Servono {$unit} cr."];
            }
            $esito = self::payAndApply((int) $player['id'], $unit, "UPDATE ships SET {$col} = ? WHERE id = ? AND {$col} <> ?", [$spec['enum'], $ship['id'], $spec['enum']]);
            return $esito === 'ok' ? ['ok' => true, 'item' => $item, 'cost' => $unit]
                : ['ok' => false, 'error' => $esito === 'crediti' ? "Servono {$unit} cr." : 'Gia\' installato.'];
        }

        // articolo a quantita' (sonde, mine)
        $cap = GameConfig::int($spec['cap'], 9999);
        $room = $cap - (int) $grezza[$col];
        if ($room <= 0) {
            return ['ok' => false, 'error' => "Capacita\' massima ({$cap}) raggiunta."];
        }
        $qty = max(1, min($qty, $room));
        $cost = $qty * $unit;
        if ((int) $player['credits'] < $cost) {
            $aff = $unit > 0 ? intdiv((int) $player['credits'], $unit) : 0;
            return ['ok' => false, 'error' => "Servono {$cost} cr; puoi permetterti {$aff}."];
        }
        $esito = self::payAndApply((int) $player['id'], $cost,
            "UPDATE ships SET {$col} = {$col} + ? WHERE id = ? AND {$col} + ? <= ?", [$qty, $ship['id'], $qty, $cap]);
        return $esito === 'ok' ? ['ok' => true, 'item' => $item, 'qty' => $qty, 'cost' => $cost]
            : ['ok' => false, 'error' => $esito === 'crediti' ? "Servono {$cost} cr." : "Capacita\' massima ({$cap}) raggiunta."];
    }

    /**
     * Paga e consegna come operazione unica: se il saldo non copre il prezzo
     * non viene toccato nulla, e se la consegna fallisce l'addebito rientra.
     *
     * @param list<mixed> $params parametri della query di consegna
     */
    private static function payAndApply(int $playerId, int $cost, string $applySql, array $params): string
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if (!Wallet::charge($playerId, ['credits' => $cost])) {
                $pdo->rollBack();
                return 'crediti';
            }
            // La consegna porta il suo vincolo nella WHERE: se non morde (gia'
            // installato, tetto raggiunto da un acquisto parallelo) l'addebito
            // rientra. Prima si pagava due volte lo stesso dispositivo.
            if (Database::run($applySql, $params)->rowCount() === 0) {
                $pdo->rollBack();
                return 'capienza';
            }
            $pdo->commit();
            return 'ok';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
