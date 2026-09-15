<?php

declare(strict_types=1);

/**
 * Clock — reperto 03 dell'audit del 2026-09-15.
 *
 * Il difetto: i lavori del tick giravano in sequenza dentro un solo try, e il
 * primo che sollevava un'eccezione impediva a tutti i successivi di girare,
 * per sempre. Riscontrato nei dati reali: il 2026-08-29 quattro minuti di fila
 * su una tabella mancante, il 2026-09-10 su una colonna mancante — e in quelle
 * finestre contratti, classifiche e notiziario semplicemente non sono esistiti.
 *
 * Qui si verifica l'isolamento: un lavoro rotto resta un lavoro rotto.
 *
 * Nota: gc() non viene esercitato perche' opera per disegno sulle righe vere
 * di tick_runs ed e' throttlato a una volta l'ora; verificarlo qui vorrebbe
 * dire cancellare dati reali o non fare nulla.
 */

use App\Core\Database;
use App\Game\TickHealth;

return static function (): void {
    Esito::sezione('Reperto 03 — un lavoro rotto non ferma gli altri');

    Esito::scenario('tre lavori, quello di mezzo solleva un\'eccezione');
    $eseguiti = [];
    $out = TickHealth::esegui([
        'primo'  => static function () use (&$eseguiti) {
            $eseguiti[] = 'primo';
            return 1;
        },
        'rotto'  => static function () use (&$eseguiti) {
            $eseguiti[] = 'rotto';
            throw new RuntimeException('guasto simulato');
        },
        'ultimo' => static function () use (&$eseguiti) {
            $eseguiti[] = 'ultimo';
            return 2;
        },
    ]);

    Esito::uguale('tutti e tre i lavori vengono tentati', ['primo', 'rotto', 'ultimo'], $eseguiti);
    Esito::verifica('il lavoro dopo quello rotto produce il suo risultato', ($out['tasks']['ultimo'] ?? null) === 2);
    Esito::uguale('il guasto viene annotato', ['rotto' => 'guasto simulato'], $out['falliti']);
    Esito::verifica("l'errore finisce nel riepilogo del lavoro", isset($out['tasks']['rotto']['error']));

    Esito::scenario('il primo lavoro esplode: gli altri devono girare comunque');
    $out2 = TickHealth::esegui([
        'esplode' => static fn () => throw new RuntimeException('subito'),
        'a'       => static fn () => 'a',
        'b'       => static fn () => 'b',
    ]);
    Esito::uguale('i due lavori sani hanno prodotto', 'a', $out2['tasks']['a'] ?? null);
    Esito::uguale('anche il secondo', 'b', $out2['tasks']['b'] ?? null);
    Esito::uguale('un solo guasto contato', 1, count($out2['falliti']));

    Esito::sezione('Isolamento vero: transazioni lasciate aperte');

    Esito::scenario('un lavoro muore lasciando una transazione aperta');
    $dentroTransazione = null;
    $out3 = TickHealth::esegui([
        'sporca' => static function () {
            Database::pdo()->beginTransaction();
            throw new RuntimeException('morto a transazione aperta');
        },
        'dopo'   => static function () use (&$dentroTransazione) {
            $dentroTransazione = Database::pdo()->inTransaction();
            return 'ok';
        },
    ]);

    Esito::verifica('il lavoro successivo gira', ($out3['tasks']['dopo'] ?? null) === 'ok');
    Esito::verifica(
        'e non eredita la transazione del lavoro morto',
        $dentroTransazione === false,
        $dentroTransazione === true ? 'isolamento solo apparente' : ''
    );
    Esito::verifica('nessuna transazione resta aperta alla fine', Database::pdo()->inTransaction() === false);

    Esito::sezione('Riepiloghi compatti');

    Esito::scenario('un lavoro che non aveva niente da fare ritorna null');
    $out4 = TickHealth::esegui([
        'niente' => static fn () => null,
        'roba'   => static fn () => ['fatto' => 3],
    ]);
    Esito::verifica('non viene annotato', !array_key_exists('niente', $out4['tasks']));
    Esito::verifica('gli altri sì', isset($out4['tasks']['roba']));
    Esito::uguale('nessun guasto', 0, count($out4['falliti']));

    Esito::sezione('Stato di salute per il pannello');

    $stato = TickHealth::stato();
    Esito::verifica('riporta uno stato noto', in_array($stato['stato'], ['regolare', 'degradato', 'avaria', 'fermo', 'sconosciuto'], true), $stato['stato']);
    Esito::verifica('conta le corse delle ultime 24 ore', is_int($stato['corse_24h']) && $stato['corse_24h'] >= 0, (string) $stato['corse_24h']);
    Esito::verifica('espone i fallimenti consecutivi', is_int($stato['consecutivi']));
    Esito::verifica('espone la durata media', is_int($stato['durata_media_ms']));
    Esito::verifica('sa quante righe ha il diario', is_int($stato['righe']) && $stato['righe'] > 0, (string) $stato['righe']);
};
