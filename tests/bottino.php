<?php

declare(strict_types=1);

/**
 * Il bottino oltre i moduli (07/10/2026): consumabili monouso, reperti e
 * progetti d'Officina. Solo comandanti sintetici.
 */

use App\Core\Database;
use App\Game\Consumabili;
use App\Game\Legge;
use App\Game\PlayerService;

return static function (): void {
    $sd = (int) Database::first('SELECT id FROM sectors WHERE is_stardock = 1 LIMIT 1')['id'];
    $rileggi = static fn (int $pid): array => Database::first('SELECT * FROM players WHERE id = ?', [$pid]);

    Esito::sezione('Consumabili — si trovano, si accumulano, si usano una volta');

    [$p, $s] = Finti::comandante(0, ['fighters' => 100, 'shields' => 10], $sd);
    $pid = (int) $p['id'];
    Consumabili::aggiungi($pid, 'nanoriparatore', 2);
    Consumabili::aggiungi($pid, 'container_caccia');
    $inv = array_column(Consumabili::inventario($pid), 'qty', 'ckey');
    ksort($inv);
    Esito::uguale('due nanoriparatori e un container in stiva', ['container_caccia' => 1, 'nanoriparatore' => 2], $inv);

    $r = Consumabili::usa($rileggi($pid), 'nanoriparatore');
    $nave = PlayerService::ship((int) $s['id']);
    Esito::verifica('i nanoriparatori riportano gli scudi al massimo', !empty($r['ok']) && (int) $nave['shields'] === (int) $nave['max_shields'],
        (string) $nave['shields']);
    $r2 = Consumabili::usa($rileggi($pid), 'nanoriparatore');
    Esito::verifica('e con gli scudi pieni il secondo non si spreca', empty($r2['ok']));
    Esito::uguale('ne resta uno', 1, (int) array_column(Consumabili::inventario($pid), 'qty', 'ckey')['nanoriparatore']);

    Consumabili::usa($rileggi($pid), 'container_caccia');
    Esito::uguale('il container porta 1.000 caccia (un decimo del tetto)', 1100,
        (int) Database::first('SELECT fighters FROM ships WHERE id = ?', [(int) $s['id']])['fighters']);
    $r3 = Consumabili::usa($rileggi($pid), 'container_caccia');
    Esito::verifica('un consumabile finito non si usa', empty($r3['ok']));

    Esito::scenario('carica EMP, acceleratore, esca, amnistia');
    foreach (['carica_emp', 'acceleratore', 'esca', 'codice_amnistia'] as $k) {
        Consumabili::aggiungi($pid, $k);
    }
    Consumabili::usa($rileggi($pid), 'carica_emp');
    Consumabili::usa($rileggi($pid), 'acceleratore');
    Consumabili::usa($rileggi($pid), 'esca');
    $attesa = array_column(Database::all('SELECT effect FROM crew_pending WHERE player_id = ? AND expires_at > NOW()', [$pid]), 'effect');
    sort($attesa);
    Esito::uguale('restano in attesa del prossimo evento', ['esca', 'free_warp', 'no_engage'], $attesa);
    Esito::verifica('l\'esca e\' attiva', Consumabili::escaAttiva($pid));
    Esito::verifica('senza notorieta\' il codice di amnistia non si spreca', empty(Consumabili::usa($rileggi($pid), 'codice_amnistia')['ok']));
    Database::run('UPDATE players SET notorieta = 150, notorieta_at = NOW() WHERE id = ?', [$pid]);
    Consumabili::usa($rileggi($pid), 'codice_amnistia');
    Esito::verifica('con 150 punti ne lascia 90', abs(Legge::puntiDi($pid) - 90) < 0.5, (string) round(Legge::puntiDi($pid), 1));

    Esito::scenario('dal bottino');
    [$q] = Finti::comandante(0, [], $sd);
    $trovati = 0;
    for ($i = 0; $i < 60; $i++) {
        $trovati += Consumabili::tira((int) $q['id'], 5) !== null ? 1 : 0;
    }
    // 25% x 1,7 nell'Orlo = 42,5%: su 60 tiri, fra 10 e 45 con margine larghissimo
    Esito::verifica('nell\'Orlo cade spesso un consumabile', $trovati >= 10 && $trovati <= 45, "{$trovati} su 60");

    Esito::sezione('Reperti — si vendono o si collezionano');

    [$r] = Finti::comandante(0, [], $sd);
    $rid = (int) $r['id'];
    foreach (array_keys(array_filter(\App\Game\Reperti::CATALOGO, static fn ($x) => $x[3] === 'federazione')) as $k) {
        \App\Game\Reperti::aggiungi($rid, $k);
    }
    \App\Game\Reperti::aggiungi($rid, 'medaglia_ammiraglio');
    $v = \App\Game\Reperti::vendi($rileggi($rid), 'medaglia_ammiraglio', 1);
    Esito::verifica('una medaglia in piu\' si vende allo StarDock per 1.500 cr', !empty($v['ok']) && (int) $rileggi($rid)['credits'] === 1500);
    $c = \App\Game\Reperti::completa($rileggi($rid), 'federazione');
    $dopo = $rileggi($rid);
    Esito::verifica('la collezione completa paga 25.000 cr e 500 exp', !empty($c['ok']) && (int) $dopo['credits'] === 26500 && (int) $dopo['experience'] === 500,
        (string) ($c['error'] ?? ''));
    Esito::verifica('e un modulo almeno Militare', !empty($c['modulo']) && $c['modulo']['rarity'] !== 'civ', (string) ($c['modulo']['rarity'] ?? '-'));
    Esito::uguale('consumando i cinque reperti', [], \App\Game\Reperti::posseduti($rid));
    foreach (array_keys(array_filter(\App\Game\Reperti::CATALOGO, static fn ($x) => $x[3] === 'federazione')) as $k) {
        \App\Game\Reperti::aggiungi($rid, $k);
    }
    Esito::verifica('una volta sola', empty(\App\Game\Reperti::completa($rileggi($rid), 'federazione')['ok']));

    Esito::sezione('Progetti — sbloccano le ricette che altrimenti restano chiuse');

    [$g, $gs] = Finti::comandante(10_000_000, [], $sd);
    $gid = (int) $g['id'];
    Esito::verifica('vicino a Sol non se ne trovano', \App\Game\Reperti::tiraProgetto($gid, 2, true) === null);
    $chiuse = array_filter(\App\Game\Industry::recipes($rileggi($gid), PlayerService::ship((int) $gs['id'])), static fn ($x) => !empty($x['progetto']));
    Esito::verifica('senza progetti le dieci ricette sono chiuse', count($chiuse) === 10 && array_filter($chiuse, static fn ($x) => $x['unlocked']) === []);
    $pg = \App\Game\Reperti::tiraProgetto($gid, 5, true);
    $aperte = array_filter(\App\Game\Industry::recipes($rileggi($gid), PlayerService::ship((int) $gs['id'])), static fn ($x) => !empty($x['progetto']) && $x['unlocked']);
    Esito::verifica('un\'elite dell\'Orlo lascia un progetto, che apre la sua ricetta', $pg !== null && array_column($aperte, 'ckey') === [$pg['key']],
        (string) ($pg['key'] ?? '-'));
    $altra = array_values(array_filter(array_column($chiuse, 'ckey'), static fn ($k) => $k !== ($pg['key'] ?? '')))[0];
    $rc = \App\Game\Industry::craft($rileggi($gid), PlayerService::ship((int) $gs['id']), $altra);
    Esito::verifica('le altre restano chiuse anche forzando la richiesta', empty($rc['ok']) && str_contains((string) $rc['error'], 'progetto'), (string) ($rc['error'] ?? ''));

    Database::run("DELETE FROM crew_pending WHERE player_id IN (SELECT id FROM players WHERE handle LIKE '\\_\\_test\\_%')");
};
