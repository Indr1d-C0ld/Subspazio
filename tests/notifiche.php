<?php

declare(strict_types=1);

/**
 * Notifiche in tempo reale: lo stream SSE consegna ogni evento una volta sola.
 *
 * Ogni pagina apre il suo stream. Dopo l'audit del 23/09 la rilettura
 * all'indietro (che recupera gli eventi scritti dentro una transazione)
 * partiva con la memoria vuota: a ogni cambio di schermata tornavano tutte le
 * notifiche dell'ultima ora. Le prove seguono lo stream giro per giro, come fa
 * l'endpoint, senza aprire una connessione HTTP.
 */

use App\Core\Database;
use App\Game\Live;

return static function (): void {
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
    $titoli = static fn (array $evs): array => array_column($evs, 'title');

    try {
        Esito::sezione('Stream — un cambio di pagina non ripresenta il passato');

        [$p] = Finti::comandante(0, [], $sd);
        $pid = (int) $p['id'];
        Live::alert($pid, 'attacked', 'Vecchio avviso', 'di prima');
        Live::sector($sd, 'move_in', null, 'vecchio arrivo nel settore');

        // La pagina nuova apre lo stream senza cursore: si parte dal piu' recente.
        $stato = Live::statoStream(Live::lastId());
        $primo = Live::giro($rileggi($pid), $stato);
        // Prima tornavano entrambi, e poi tutto il resto dell'ultima ora.
        Esito::uguale('il primo giro non manda niente di gia\' esistente', [], $titoli($primo));

        Live::alert($pid, 'attacked', 'Avviso nuovo', 'adesso');
        $secondo = Live::giro($rileggi($pid), $stato);
        Esito::uguale('un evento nuovo arriva', ['Avviso nuovo'], $titoli($secondo));
        Esito::uguale('una volta sola', [], $titoli(Live::giro($rileggi($pid), $stato)));

        Esito::scenario('riconnessione dopo cinque minuti, con Last-Event-ID');
        $cursore = (int) end($secondo)['cursore'];
        $ripreso = Live::statoStream($cursore);
        Esito::uguale('non rimanda cio\' che il client ha gia\' avuto', [], $titoli(Live::giro($rileggi($pid), $ripreso)));

        Esito::scenario('ripresa dalla pagina precedente, con un evento arrivato nel frattempo');
        Live::alert($pid, 'attacked', 'Durante il cambio', 'mentre la pagina caricava');
        $ripreso = Live::statoStream($cursore);
        Esito::uguale('arriva solo quello', ['Durante il cambio'], $titoli(Live::giro($rileggi($pid), $ripreso)));

        Esito::sezione('Stream — un evento scritto in una transazione non si perde');

        // Un altro processo scrive un evento e tiene aperta la transazione;
        // intanto ne arriva uno successivo, gia' visibile. Lo stream parte
        // dopo entrambi i numeri, ma il primo diventa visibile solo al commit.
        $t0 = microtime(true) + 0.3;
        $proc = proc_open(['php', dirname(__DIR__) . '/tests/_corsa.php', 'evento_lento', (string) $t0, (string) $pid, '1500'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        usleep((int) (($t0 - microtime(true)) * 1e6) + 500_000);
        Live::alert($pid, 'attacked', 'Evento veloce', 'gia\' visibile');
        $stato = Live::statoStream(Live::lastId());
        Esito::uguale('all\'apertura non c\'e\' niente di nuovo', [], $titoli(Live::giro($rileggi($pid), $stato)));
        $esito = proc_close($proc);
        Esito::uguale('il processo lento ha concluso', 0, $esito);
        Esito::uguale('dopo il commit l\'evento lento arriva', ['Evento lento'], $titoli(Live::giro($rileggi($pid), $stato)));
        Esito::uguale('e non torna', [], $titoli(Live::giro($rileggi($pid), $stato)));

        Esito::sezione('Client — la scheda ricorda cio\' che ha mostrato (statica)');
        $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/live.js');
        Esito::verifica('scarta gli eventi gia\' visti', str_contains($js, 'if (giaVisto(d.eid)) return;'));
        Esito::verifica('riprende dal cursore della pagina precedente', str_contains($js, "'?last=' + encodeURIComponent(c.id)"));
    } finally {
        Database::run("DELETE le FROM live_events le JOIN players p ON le.scope = 'player' AND p.id = le.scope_id WHERE p.handle LIKE '\\_\\_test\\_%'");
        Database::run("DELETE FROM live_events WHERE scope = 'sector' AND body = 'vecchio arrivo nel settore'");
        Database::run("DELETE a FROM alerts a JOIN players p ON p.id = a.player_id WHERE p.handle LIKE '\\_\\_test\\_%'");
    }
};
