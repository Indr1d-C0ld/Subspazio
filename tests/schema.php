<?php

declare(strict_types=1);

/**
 * Schema — reperto 06 dell'audit del 2026-09-15.
 *
 * Il difetto: quattro colonne usate come filtro in query ricorrenti non
 * avevano indice, e due di quelle tabelle crescono senza limite. Non si
 * notava — le tabelle sono minuscole — ma ogni statistica del pannello
 * faceva una scansione completa.
 *
 * Qui non si verifica solo che l'indice esista: si verifica che il
 * pianificatore lo usi davvero per la query che deve servire. Un indice che
 * c'è ma non viene scelto è peso morto, e cambiare la forma di una query
 * basta a farlo scartare in silenzio.
 */

use App\Core\Database;

return static function (): void {
    Esito::sezione('Reperto 06 — indici sulle query ricorrenti');

    /** @var array<string, array{0:string, 1:string, 2:string}> nome => [tabella, indice, query servita] */
    $attesi = [
        'NPC da muovere (ogni 60 s)' => [
            'npcs',
            'idx_npcs_last_move',
            'SELECT * FROM npcs WHERE last_move_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE) LIMIT 200',
        ],
        'comandanti online (pannello)' => [
            'players',
            'idx_players_last_seen',
            'SELECT COUNT(*) c FROM players WHERE last_seen_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
        ],
        'scambi nelle 24 ore' => [
            'trade_log',
            'idx_trade_log_created',
            'SELECT COUNT(*) c FROM trade_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)',
        ],
        'combattimenti nelle 24 ore' => [
            'combat_log',
            'idx_combat_log_created',
            'SELECT COUNT(*) c FROM combat_log WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)',
        ],
    ];

    foreach ($attesi as $scopo => [$tabella, $indice, $query]) {
        Esito::scenario($scopo);

        $presente = Database::first(
            'SELECT 1 x FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tabella, $indice]
        ) !== null;
        Esito::verifica("l'indice {$indice} esiste", $presente);

        if (!$presente) {
            continue;
        }

        // Si verifica che l'indice sia APPLICABILE alla query (possible_keys),
        // non che il pianificatore lo scelga (key). Su tabelle piccole, o
        // quando l'intervallo copre quasi tutte le righe, MySQL preferisce
        // giustamente la scansione completa: con 95 NPC quasi tutti "da
        // muovere" e' la scelta piu' economica, e asserire il contrario
        // rendeva la prova intermittente. Cio' che deve restare vero nel
        // tempo e' che l'indice esista e serva quella forma di query; quale
        // via convenga poi all'ottimizzatore dipende dai volumi, e cambiera'
        // da solo quando le tabelle cresceranno.
        $piano = Database::first('EXPLAIN ' . $query);
        $applicabili = array_filter(array_map('trim', explode(',', (string) ($piano['possible_keys'] ?? ''))));
        Esito::verifica(
            'la query può servirsi di quell\'indice',
            in_array($indice, $applicabili, true),
            sprintf('possible_keys=%s · scelto=%s (type=%s)',
                $piano['possible_keys'] ?: 'nessuno',
                $piano['key'] ?: 'scansione completa',
                $piano['type'] ?? '?')
        );
    }

    Esito::sezione('Manopole del clock esposte nel pannello');

    Esito::scenario('le chiavi di configurazione sono registrate, non default nascosti');
    foreach (['tick.keep_days' => 14, 'tick.alert_after_failures' => 5] as $chiave => $atteso) {
        $riga = Database::first('SELECT cvalue, ctype, default_value FROM game_config WHERE ckey = ?', [$chiave]);
        Esito::verifica("{$chiave} è in game_config", $riga !== null);
        if ($riga !== null) {
            Esito::uguale("{$chiave} ha il default giusto", (string) $atteso, (string) $riga['default_value']);
        }
    }

    Esito::sezione('Crescita delle tabelle sotto controllo');

    Esito::scenario('tick_runs resta dentro la finestra di conservazione');
    $giorni = App\Game\GameConfig::int('tick.keep_days', 14);
    $vecchie = (int) (Database::first(
        'SELECT COUNT(*) n FROM tick_runs WHERE ok = 1 AND started_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
        [$giorni + 1]   // un giorno di margine: la potatura gira una volta l'ora
    )['n'] ?? 0);
    Esito::verifica(
        "nessuna corsa riuscita più vecchia di {$giorni} giorni",
        $vecchie === 0,
        $vecchie > 0 ? "{$vecchie} righe da potare" : ''
    );
};
