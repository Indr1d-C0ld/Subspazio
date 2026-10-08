<?php

declare(strict_types=1);

/**
 * Traguardi, contatori di carriera e titoli (08/10/2026).
 *
 * Prima: 18 traguardi, tutte soglie semplici, e niente su fasce, elite,
 * legge, moduli rari o collezioni.
 */

use App\Core\Database;
use App\Game\Achievements;
use App\Game\Economy;
use App\Game\Navigation;
use App\Game\PlayerService;
use App\Game\Stats;

return static function (): void {
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);

    Esito::sezione('Catalogo — ricco, a livelli, con segreti');

    $n = (int) Database::first('SELECT COUNT(*) n FROM achievements')['n'];
    Esito::verifica('oltre cento traguardi', $n > 100, (string) $n);
    Esito::verifica('in sette categorie', (int) Database::first('SELECT COUNT(DISTINCT categoria) n FROM achievements')['n'] === 7);
    Esito::verifica('con tutti e quattro i livelli', (int) Database::first('SELECT COUNT(DISTINCT livello) n FROM achievements WHERE livello IS NOT NULL')['n'] === 4);
    Esito::verifica('e alcuni segreti', (int) Database::first('SELECT COUNT(*) n FROM achievements WHERE segreto = 1')['n'] >= 5);

    // ogni contatore letto da un traguardo deve essere incrementato da qualche parte
    $codice = '';
    foreach (glob(dirname(__DIR__) . '/src/Game/*.php') as $f) {
        $codice .= file_get_contents($f);
    }
    $orfani = [];
    foreach (Database::all('SELECT DISTINCT contatore FROM achievements WHERE contatore IS NOT NULL') as $r) {
        $c = (string) $r['contatore'];
        $dinamico = str_starts_with($c, 'moduli_') && str_contains($codice, "'moduli_' . ");
        if (!$dinamico && !str_contains($codice, "'{$c}'")) {
            $orfani[] = $c;
        }
    }
    Esito::uguale('nessun traguardo irraggiungibile: ogni contatore ha chi lo incrementa', [], $orfani);

    Esito::sezione('Contatori di carriera — gli eventi di gioco li alimentano');

    [$p] = Finti::comandante(0, [], $sd);
    $pid = (int) $p['id'];
    Stats::add($pid, 'prova', 3);
    Stats::add($pid, 'prova', 2);
    Stats::max($pid, 'prova_max', 4);
    Stats::max($pid, 'prova_max', 2);
    $st = Stats::di($pid);
    Esito::uguale('si sommano, e il massimo resta il massimo', [5, 4], [$st['prova'] ?? 0, $st['prova_max'] ?? 0]);

    $porto = Database::first("SELECT p.sector_id FROM ports p JOIN sectors s ON s.id = p.sector_id WHERE p.ore_mode = 'sell' AND p.ore_stock > 20 AND p.destroyed = 0 LIMIT 1");
    [$c, $cs] = Finti::comandante(100000, [], (int) $porto['sector_id']);
    $r = Economy::settle($rileggi((int) $c['id']), PlayerService::ship((int) $cs['id']), (int) $porto['sector_id'], 'ore', 'buy', 3, null);
    $sc = Stats::di((int) $c['id']);
    Esito::verifica('uno scambio conta: scambi e volume', !empty($r['ok']) && ($sc['scambi'] ?? 0) === 1 && ($sc['volume_commercio'] ?? 0) === (int) $r['total'],
        json_encode($sc));

    $tratta = Database::first('SELECT w.from_sector a, w.to_sector b FROM warps w JOIN sectors t ON t.id = w.to_sector WHERE t.band = 2 LIMIT 1');
    [$v, $vs] = Finti::comandante(0, [], (int) $tratta['a']);
    Navigation::move($rileggi((int) $v['id']), PlayerService::ship((int) $vs['id']), (int) $tratta['b']);
    $sv = Stats::di((int) $v['id']);
    Esito::verifica('un salto conta: salti, settore scoperto, fascia piu\' lontana', ($sv['salti'] ?? 0) === 1
        && ($sv['settori_scoperti'] ?? 0) === 1 && ($sv['fascia_max'] ?? 0) === 2, json_encode($sv));

    Esito::sezione('Sblocco e titoli');

    [$t] = Finti::comandante(0, [], $sd);
    $tid = (int) $t['id'];
    Stats::add($tid, 'scambi', 30);
    $nuovi = Achievements::evaluateContatori($tid);
    Esito::verifica('30 scambi: Bottegaio si, Mercante no', in_array('scambi_bronzo', $nuovi, true) && !in_array('scambi_argento', $nuovi, true),
        implode(',', $nuovi));
    Esito::verifica('senza punti, nessun titolo', !in_array('Navigatore', Achievements::titoliDisponibili($tid), true));
    Esito::verifica('un titolo non guadagnato non si sceglie', empty(Achievements::scegliTitolo($rileggi($tid), 'Mito dell\'Orlo')['ok']));
    Stats::max($tid, 'gradino_max', 4);
    Achievements::evaluateContatori($tid);
    $titoli = Achievements::titoliDisponibili($tid);
    Esito::verifica('Nemico pubblico da\' il suo titolo', in_array('Nemico Pubblico', $titoli, true), implode(',', $titoli));
    $sc = Achievements::scegliTitolo($rileggi($tid), 'Nemico Pubblico');
    Esito::uguale('e lo si mostra accanto al nome', 'Nemico Pubblico', $rileggi($tid)['titolo']);
    Esito::verifica('con 50 punti arriva «Navigatore»', Achievements::points($tid) < 50 || in_array('Navigatore', $titoli, true));
};
