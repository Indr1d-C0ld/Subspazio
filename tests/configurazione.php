<?php

declare(strict_types=1);

/**
 * La configurazione deve dire la verita'.
 *
 * Tre modi in cui una manopola mente, tutti trovati davvero nell'audit del
 * 2026-09-19:
 *
 *   - esiste in tabella e il codice non la legge (nel pannello sembra
 *     comandare qualcosa e non risponde — era il caso di registration.open,
 *     che lasciava "chiudere" le iscrizioni senza chiuderle);
 *   - il ripiego scritto nel codice non e' il valore voluto (e allora la
 *     regola cambia da sola il giorno in cui la riga non c'e');
 *   - la stessa chiave viene letta con ripieghi diversi in punti diversi
 *     (e allora la stessa azione costa cose diverse a seconda della strada).
 *
 * Questa prova rilegge tutto il codice e confronta. Non serve tenerla
 * aggiornata a mano: cresce da sola con il progetto.
 */

use App\Core\Database;
use App\Game\GameConfig;
use App\Game\Ranks;

return static function (): void {
    $ROOT = dirname(__DIR__);

    // Chiavi composte a pezzi nel codice ("scan.{$kind}_target_{$banda}"):
    // cercarne il nome intero non le trova, ma sono vive. Verificate a mano.
    $dinamiche = [
        '/^economy\.anchor\./',
        '/^loot\.drop_chance_/',
        '/^scan\.\w+_target_/',
        '/^mine\.asteroid_target_/',
    ];

    // Eccezioni motivate, non scuse: ognuna dice perche'.
    $senzaConfronto = [
        // Il ripiego non e' un valore ma un segnaposto da mostrare a schermo
        // quando l'universo non e' mai stato generato.
        'universe.generated_at' => 'il ripiego e\' una dicitura, non un valore',
        'economy.generated_at'  => 'il ripiego e\' una dicitura, non un valore',
    ];

    // L'unica manopola che sopravvive scollegata, per scelta: le tre fasce
    // esistono, ma il nome non descrive cio' che il generatore fa davvero
    // (una regione profonda ogni tre, non "tre bande"). Collegarla vorrebbe
    // dire prima decidere cosa debba significare.
    $scollegataDiProposito = ['universe.region_bands'];

    // --- lettura del codice ------------------------------------------------

    $sorgenti = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT)) as $f) {
        $p = $f->getPathname();
        if (!preg_match('/\.(php|js|json|webmanifest|css)$/', $p)) {
            continue;
        }
        if (str_contains($p, '/storage/') || str_contains($p, '/.git/')
            || str_contains($p, '/db/migrations/') || str_contains($p, '/tests/')) {
            continue;
        }
        $sorgenti[str_replace($ROOT . '/', '', $p)] = (string) file_get_contents($p);
    }

    $letti = [];
    $rx = '/(?:Game)?Config::(int|float|bool|str)\(\s*[\'"]([a-z0-9_.]+)[\'"]\s*(?:,\s*([^),]*))?/i';
    foreach ($sorgenti as $rel => $src) {
        if (!preg_match_all($rx, $src, $m, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($m as $x) {
            $d = isset($x[3]) && trim($x[3]) !== '' ? trim($x[3]) : null;
            $letti[$x[2]]['default'][$d ?? '(nessuno)'] = true;
            $letti[$x[2]]['dove'][$rel] = true;
        }
    }

    $tabella = [];
    foreach (Database::all('SELECT ckey, cvalue, ctype, default_value FROM game_config ORDER BY ckey') as $r) {
        $tabella[$r['ckey']] = $r;
    }

    /** Confronta solo i ripieghi LETTERALI: costanti ed espressioni si saltano. */
    $letterale = static function (mixed $v): ?string {
        // Attenzione: arrivano da array_keys(), e PHP converte in intero le
        // chiavi che sembrano numeri. Si normalizza prima di guardarle.
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        if ($v === '' || $v === '(nessuno)') {
            return null;
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $v)) {
            return rtrim(rtrim(sprintf('%.6f', (float) $v), '0'), '.') ?: '0';
        }
        if (preg_match('/^[\'"](.*)[\'"]$/s', $v, $m)) {
            return $m[1];
        }
        if (in_array(strtolower($v), ['true', 'false'], true)) {
            return strtolower($v) === 'true' ? '1' : '0';
        }
        return null;                       // self::COSTANTE, 2 * 1024, match(...)
    };

    Esito::sezione('Configurazione — ogni manopola comanda qualcosa');

    Esito::scenario('nessuna chiave in tabella che il codice non legga');
    $morte = [];
    foreach ($tabella as $k => $_) {
        if (isset($letti[$k]) || in_array($k, $scollegataDiProposito, true)) {
            continue;
        }
        foreach ($dinamiche as $d) {
            if (preg_match($d, $k)) {
                continue 2;
            }
        }
        // ultima possibilita': citata altrove (stato scritto da set(), viste)
        foreach ($sorgenti as $src) {
            if (str_contains($src, $k)) {
                continue 2;
            }
        }
        $morte[] = $k;
    }
    Esito::uguale('manopole scollegate', 0, count($morte));
    if ($morte !== []) {
        Esito::verifica('  elenco: ' . implode(', ', $morte), false);
    }

    Esito::scenario('le sei superate dall\'audit non sono tornate');
    foreach ([
        'crew.mission_refresh_hours', 'season.regen_universe', 'craft.industry_last_run',
        'map.node_radius', 'game.name', 'game.tagline',
    ] as $k) {
        Esito::verifica("«{$k}» resta fuori dalla tabella", !isset($tabella[$k]));
    }

    Esito::sezione('Configurazione — i ripieghi dicono il valore voluto');

    Esito::scenario('il secondo argomento di GameConfig:: e\' la regola del giorno in cui la riga manca');
    $divergenti = [];
    foreach ($letti as $k => $v) {
        if (!isset($tabella[$k]) || isset($senzaConfronto[$k])) {
            continue;
        }
        $atteso = $letterale($tabella[$k]['default_value']);
        if ($atteso === null) {
            continue;
        }
        foreach (array_keys($v['default']) as $d) {
            $codice = $letterale($d);
            if ($codice !== null && $codice !== $atteso) {
                $divergenti[] = "{$k} (tabella {$atteso}, codice {$codice}) in " . implode(' ', array_keys($v['dove']));
            }
        }
    }
    Esito::uguale('ripieghi divergenti', 0, count($divergenti));
    foreach ($divergenti as $d) {
        Esito::verifica('  ' . $d, false);
    }

    Esito::scenario('la stessa chiave non ha ripieghi diversi in punti diversi');
    $discordi = [];
    foreach ($letti as $k => $v) {
        $valori = array_filter(array_map($letterale, array_keys($v['default'])), static fn ($x) => $x !== null);
        if (count(array_unique($valori)) > 1) {
            $discordi[] = "{$k}: " . implode(' / ', array_unique($valori));
        }
    }
    Esito::uguale('chiavi con ripieghi discordi', 0, count($discordi));
    foreach ($discordi as $d) {
        Esito::verifica('  ' . $d, false);
    }

    // --- allineamento -------------------------------------------------------

    Esito::sezione('Allineamento — l\'etichetta e il trattamento sono la stessa cosa');

    $evilPrima = GameConfig::str('ranks.evil_threshold', '-100');
    $goodPrima = GameConfig::str('ranks.good_threshold', '100');
    try {
        Esito::scenario('la soglia dei buoni comanda davvero l\'etichetta');
        GameConfig::set('ranks.good_threshold', '50');
        GameConfig::forget();
        Esito::uguale('a 60 si e\' gia\' Benefattore con soglia 50', 'Benefattore', Ranks::alignmentLabel(60));
        GameConfig::set('ranks.good_threshold', '100');
        GameConfig::forget();
        Esito::uguale('con soglia 100 si torna Neutrale', 'Neutrale', Ranks::alignmentLabel(60));

        Esito::scenario('chi e\' chiamato fuorilegge viene trattato da fuorilegge');
        // Prima, l'etichetta aveva le soglie scritte a mano mentre isEvil()
        // leggeva la manopola: spostandola si otteneva un comandante chiamato
        // «Fuorilegge» che i cannoni planetari trattavano da neutrale.
        foreach ([['-100', '100'], ['-300', '50'], ['-700', '100'], ['-50', '10']] as [$ev, $go]) {
            GameConfig::set('ranks.evil_threshold', $ev);
            GameConfig::set('ranks.good_threshold', $go);
            GameConfig::forget();
            $coerente = true;
            foreach ([-900, -800, -400, -150, -50, 0, 60, 120, 600, 900] as $a) {
                $etichettaCattiva = in_array(Ranks::alignmentLabel($a), ['Fuorilegge', 'Corsaro'], true);
                if ($etichettaCattiva !== Ranks::isEvil($a)) {
                    $coerente = false;
                    break;
                }
            }
            Esito::verifica("soglie {$ev}/{$go}: etichetta e trattamento concordano", $coerente);
        }

        Esito::scenario('una manopola messa male non inverte la scala');
        // Al massimo una fascia si svuota: mai «Corsaro» sopra «Fuorilegge».
        GameConfig::set('ranks.evil_threshold', '-700');
        GameConfig::set('ranks.good_threshold', '100');
        GameConfig::forget();
        $scala = array_map(static fn (int $a): string => Ranks::alignmentLabel($a), [-900, -400, 0, 200, 900]);
        Esito::uguale('agli estremi le etichette restano quelle giuste',
            ['Corsaro', 'Eroe della Federazione'], [$scala[0], $scala[4]]);
        Esito::verifica('e nel mezzo non compaiono salti all\'indietro',
            $scala[1] === 'Neutrale' && $scala[2] === 'Neutrale' && $scala[3] === 'Benefattore',
            implode(' · ', $scala));
    } finally {
        GameConfig::set('ranks.evil_threshold', $evilPrima);
        GameConfig::set('ranks.good_threshold', $goodPrima);
        GameConfig::forget();
        Esito::uguale('soglie ripristinate', [$evilPrima, $goodPrima], [
            GameConfig::str('ranks.evil_threshold', '?'),
            GameConfig::str('ranks.good_threshold', '?'),
        ]);
    }
};
