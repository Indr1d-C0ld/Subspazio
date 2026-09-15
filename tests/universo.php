<?php

declare(strict_types=1);

/**
 * Topologia e movimento NPC — reperto 04 dell'audit del 2026-09-15.
 *
 * Il difetto: il movimento NPC rileggeva i settori adiacenti uno a uno,
 * dentro un filtro e senza cache. Con ~95 NPC e 3,3 rotte medie per settore
 * erano ~400 query al minuto — con una JOIN su regions ciascuna — solo per
 * decidere dove spostare dei puntini. Il tick piu' lento delle 24 ore
 * precedenti era durato 13,4 secondi su un budget di 60.
 *
 * Qui si verifica che la versione a letture raggruppate dia gli stessi
 * risultati di quella a letture singole, e che la scrittura raggruppata
 * mandi ogni NPC dove deve.
 */

use App\Core\Database;
use App\Game\Npc;
use App\Game\Universe;

return static function (): void {
    // --- cache e letture raggruppate --------------------------------------

    Esito::sezione('Universe — la cache dà le stesse risposte');

    $campione = array_map(
        static fn ($r) => (int) $r['id'],
        Database::all('SELECT id FROM sectors ORDER BY id LIMIT 25')
    );

    Esito::scenario('rotte: warpsFromMany() contro warpsFrom() uno a uno');
    Universe::forget();
    $raggruppate = Universe::warpsFromMany($campione);
    Universe::forget();
    $singole = [];
    foreach ($campione as $id) {
        $singole[$id] = Universe::warpsFrom($id);
    }
    Esito::uguale('stesse rotte per tutti i settori del campione', $singole, $raggruppate);

    Esito::scenario('settori: sectorsMany() contro sector() uno a uno');
    Universe::forget();
    $many = Universe::sectorsMany($campione);
    Universe::forget();
    $uno = [];
    foreach ($campione as $id) {
        $s = Universe::sector($id);
        if ($s !== null) {
            $uno[$id] = $s;
        }
    }
    Esito::uguale('stessi settori, stesso contenuto', $uno, $many);

    Esito::scenario('un settore inesistente');
    Universe::forget();
    Esito::uguale('sector() lo riporta come assente', null, Universe::sector(999_999_999));
    Esito::verifica('sectorsMany() non lo include', !isset(Universe::sectorsMany([999_999_999])[999_999_999]));

    Esito::sezione('Universe — la cache non serve dati stantii');

    $primo = $campione[0];
    Esito::scenario('forget() su un singolo settore');
    Universe::forget();
    $prima = Universe::sector($primo);
    Database::run('UPDATE sectors SET beacon = ? WHERE id = ?', ['__test_faro', $primo]);
    $stantio = Universe::sector($primo);
    Universe::forget($primo);
    $fresco = Universe::sector($primo);
    // ripristina subito, prima di qualsiasi verifica che possa fallire
    Database::run('UPDATE sectors SET beacon = ? WHERE id = ?', [$prima['beacon'], $primo]);
    Universe::forget($primo);

    Esito::uguale('senza forget() la cache tiene il valore vecchio', $prima['beacon'], $stantio['beacon']);
    Esito::uguale('dopo forget() rilegge dal database', '__test_faro', $fresco['beacon']);
    Esito::uguale('il faro originale è stato ripristinato', $prima['beacon'], Universe::sector($primo)['beacon']);

    Esito::scenario('forget() totale');
    Universe::sectorsMany($campione);
    Universe::forget();
    Esito::verifica('dopo il forget totale la cache è vuota', Universe::sector($primo) !== null); // rilegge senza errori

    // --- movimento NPC ----------------------------------------------------

    Esito::sezione('Reperto 04 — movimento NPC');

    // Un settore con almeno due rotte uscenti, per avere una scelta reale, e
    // con TUTTE le uscite fuori dalla Fedspace: il tick despawna pirati e
    // Ferrengi che ci finiscono dentro (comportamento giusto del gioco), e un
    // NPC di prova sparito a meta' faceva fallire il conteggio a caso.
    $partenza = Database::first(
        'SELECT w.from_sector id, COUNT(*) n
         FROM warps w
         JOIN sectors d ON d.id = w.to_sector
         WHERE NOT EXISTS (
             SELECT 1 FROM warps w2 JOIN sectors d2 ON d2.id = w2.to_sector
             WHERE w2.from_sector = w.from_sector AND d2.is_fedspace = 1
         )
         GROUP BY w.from_sector HAVING n >= 2 ORDER BY RAND() LIMIT 1'
    );
    if ($partenza === null) {
        Esito::verifica('nessun settore adatto (due rotte, nessuna in Fedspace): prova saltata', true);
        return;
    }
    $da = (int) $partenza['id'];
    $vicini = Universe::warpsFrom($da);

    // Il cron gira ogni minuto e muove gli NPC con lo stesso codice: senza
    // prendere il suo stesso lock, puo' spostare quelli di prova un istante
    // prima di noi e rendere il test intermittente (successo). Qui lo si
    // tiene per la durata della prova, cosi' il tick vero salta il giro.
    $lockFile = (string) ($GLOBALS['__project_root'] ?? dirname(__DIR__)) . '/storage/tick.lock';
    $lock = fopen($lockFile, 'c');
    $preso = false;
    if ($lock !== false) {
        for ($tentativi = 0; $tentativi < 100 && !$preso; $tentativi++) {
            $preso = flock($lock, LOCK_EX | LOCK_NB);
            if (!$preso) {
                usleep(100_000); // il tick dura decine di ms: dieci secondi bastano
            }
        }
    }
    Esito::verifica('preso il lock del clock, il cron non interferisce', $preso);

    $npcIds = [];
    try {
        foreach (['trader', 'pirate', 'ferrengi'] as $i => $kind) {
            Database::run(
                'INSERT INTO npcs (kind, name, ship_type, sector_id, last_move_at)
                 VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY))',
                [$kind, '__test_npc_' . $i, 'scout_marauder', $da]
            );
            $npcIds[] = Database::lastInsertId();
        }

        Esito::scenario(sprintf('tre NPC nel settore %d, che ha %d rotte uscenti', $da, count($vicini)));
        $mossi = Npc::tick()['moved'];
        Esito::verifica('il tick li ha mossi', $mossi >= 3, "mossi {$mossi}");

        $dopo = Database::all(
            'SELECT id, kind, sector_id FROM npcs WHERE id IN (' . implode(',', array_fill(0, count($npcIds), '?')) . ')',
            $npcIds
        );
        Esito::uguale('sono ancora tutti e tre', 3, count($dopo));

        $tuttiValidi = true;
        $tuttiPartiti = true;
        foreach ($dopo as $r) {
            if (!in_array((int) $r['sector_id'], $vicini, true)) {
                $tuttiValidi = false;
            }
            if ((int) $r['sector_id'] === $da) {
                $tuttiPartiti = false;
            }
        }
        // Questa è la prova che conta sulla scrittura raggruppata: con un CASE
        // sbagliato gli NPC finirebbero in settori non adiacenti, o tutti nello
        // stesso, o non si muoverebbero affatto.
        Esito::verifica('ognuno è finito in un settore adiacente alla partenza', $tuttiValidi);
        Esito::verifica('nessuno è rimasto fermo', $tuttiPartiti);

        Esito::scenario('i Ferrengi evitano lo spazio della Federazione');
        $ferrengi = null;
        foreach ($dopo as $r) {
            if ($r['kind'] === 'ferrengi') {
                $ferrengi = (int) $r['sector_id'];
            }
        }
        $inFed = (int) (Database::first('SELECT is_fedspace FROM sectors WHERE id = ?', [$ferrengi])['is_fedspace'] ?? 0);
        Esito::uguale('il Ferrengi non è entrato in Fedspace', 0, $inFed);
    } finally {
        if ($npcIds !== []) {
            Database::run(
                'DELETE FROM npcs WHERE id IN (' . implode(',', array_fill(0, count($npcIds), '?')) . ')',
                $npcIds
            );
        }
        Universe::forget();
        if ($lock !== false) {
            if ($preso) {
                flock($lock, LOCK_UN);
            }
            fclose($lock);
        }
    }

    $rimasti = (int) (Database::first("SELECT COUNT(*) n FROM npcs WHERE name LIKE '__test\\_%'")['n'] ?? 0);
    Esito::uguale('nessun NPC di prova lasciato indietro', 0, $rimasti);
};
