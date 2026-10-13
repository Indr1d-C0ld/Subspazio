<?php

declare(strict_types=1);

/**
 * Ripartenza da zero (13/10/2026). L'operazione azzera il gioco vero e non
 * si chiama MAI dalle prove: si legge il sorgente. La verifica che conta e'
 * che nessuna tabella dei comandanti sfugga, nemmeno una aggiunta domani: ogni
 * tabella che punta a un comandante va svuotata dalla ripartenza o dal
 * generatore dell'universo (o e' la nave, che si riporta allo scafo iniziale).
 */

use App\Core\Database;

return static function (): void {
    $radice = (string) ($GLOBALS['__project_root'] ?? dirname(__DIR__));
    $season = (string) file_get_contents($radice . '/src/Game/Season.php');
    $i = strpos($season, 'private static function azzeraTutto');
    $ripartenza = $i === false ? '' : substr($season, $i);
    $generatore = (string) file_get_contents($radice . '/src/Game/UniverseGenerator.php');
    $porti = (string) file_get_contents($radice . '/src/Game/PortGenerator.php');

    Esito::sezione('Ripartenza — nessuna tabella dei comandanti resta indietro');

    Esito::verifica('l\'operazione c\'e\'', $ripartenza !== '');
    $tabelle = array_column(Database::all(
        "SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND COLUMN_NAME IN ('player_id', 'attacker_player_id', 'defender_player_id', 'owner_player_id',
                                'issuer_player_id', 'from_player_id', 'to_player_id', 'target_player_id')
          ORDER BY 1"
    ), 't');
    $dimenticate = array_values(array_filter($tabelle, static fn (string $t): bool => $t !== 'ships'
        && !str_contains($ripartenza, "'{$t}'") && !str_contains($generatore, "'{$t}'") && !str_contains($porti, "'{$t}'")));
    Esito::uguale(count($tabelle) . ' tabelle puntano ai comandanti: tutte svuotate', [], $dimenticate);

    Esito::sezione('Ripartenza — la storia va via, le definizioni restano');

    foreach (['seasons', 'season_results', 'player_achievements', 'player_codex', 'player_stats', 'player_reputation',
        'faction_log', 'fednews', 'messages'] as $t) {
        Esito::verifica("svuotata: {$t}", str_contains($ripartenza, "'{$t}'"));
    }
    // definizioni del gioco e account: mai toccati
    foreach (['users', 'achievements', 'codex_entries', 'recipes', 'encounters', 'faction_offers', 'item_types',
        'ship_types', 'audit_log', 'game_config', 'media_assets'] as $t) {
        Esito::verifica("intatta: {$t}", !preg_match("/TRUNCATE[^;]*'{$t}'|'{$t}',|DELETE FROM {$t}\\b/", $ripartenza));
    }
    Esito::verifica('si riparte dalla Stagione 1', str_contains($ripartenza, "INSERT INTO seasons (number, name) VALUES (1,")
        && str_contains($ripartenza, "GameConfig::set('season.number', '1')"));
    Esito::verifica('nome e aspetto restano (handle, colore, stemma, motto non si toccano)',
        !preg_match('/\b(handle|color|crest|motto)\s*=/', $ripartenza));
    Esito::verifica('i primi passi ripartono per tutti, e i titoli (da traguardi) vanno via',
        str_contains($ripartenza, 'onboarding_state = 0') && str_contains($ripartenza, 'titolo = NULL'));
    $annuncio = strpos($ripartenza, "Radio::system('RIPARTENZA");
    Esito::verifica('l\'annuncio in radio va in onda dopo lo svuotamento della radio',
        $annuncio !== false && $annuncio > (int) strpos($ripartenza, "'messages'"));
    Esito::sezione('Ripartenza — home e guida la annunciano per un mese');

    // Si sposta l'inizio della stagione solo dentro una transazione annullata.
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        Database::run("UPDATE seasons SET started_at = NOW() - INTERVAL 2 DAY WHERE status = 'active'");
        $numero = (int) (Database::first("SELECT number FROM seasons WHERE status = 'active' LIMIT 1")['number'] ?? 0);
        Esito::verifica('nei primi giorni della Stagione 1 c\'e\' l\'annuncio',
            $numero !== 1 || \App\Game\Season::ripartenzaRecente() !== null);
        Database::run("UPDATE seasons SET started_at = NOW() - INTERVAL 31 DAY WHERE status = 'active'");
        Esito::verifica('dopo un mese sparisce da solo', \App\Game\Season::ripartenzaRecente() === null);
    } finally {
        $pdo->rollBack();
    }
    Esito::verifica('home e guida leggono la stessa regola',
        str_contains((string) file_get_contents($radice . '/src/Controllers/HomeController.php'), 'Season::ripartenzaRecente()')
        && str_contains((string) file_get_contents($radice . '/views/game/guide.php'), 'Season::ripartenzaRecente()'));

    Esito::sezione('Ripartenza — l\'aiuto contestuale dice il vero');

    // L'aiuto promette che traguardi, codex e contatori restano fra le
    // stagioni e li svuota solo la ripartenza: lo si controlla sul sorgente.
    $j = strpos($season, 'private static function chiudi');
    $chiudi = $j === false ? '' : substr($season, $j);
    foreach (['player_achievements' => 'traguardi', 'player_codex' => 'codex', 'player_stats' => 'contatori di carriera'] as $t => $cosa) {
        Esito::verifica("{$cosa}: la chiusura di stagione non li tocca, la ripartenza si'",
            !preg_match("/TRUNCATE[^;]*'{$t}'|'{$t}',|DELETE FROM {$t}\\b/", $chiudi) && str_contains($ripartenza, "'{$t}'"));
    }
    Esito::verifica('la reputazione si azzera a ogni chiusura', str_contains($chiudi, 'TRUNCATE TABLE player_reputation'));
    $aiuto = static fn (string $k): string => (string) \App\Game\Help::get($k);
    foreach (['plancia.primipassi', 'traguardi.home', 'albo.home', 'codex.home', 'profilo.identita'] as $k) {
        Esito::verifica("«{$k}» parla della ripartenza", str_contains($aiuto($k), 'riparte') || str_contains($aiuto($k), 'ripartenza'), $aiuto($k));
    }

    Esito::sezione('Ripartenza — nel notiziario');

    $componi = static fn (): array => (new ReflectionMethod(\App\Game\FedNews::class, 'compose'))->invoke(null);
    $cronaca = static fn (array $t): string => (string) (array_values(array_filter($t, static fn ($x) => str_starts_with($x, 'Stagione 1, giorno')))[0] ?? '');
    $pdo->beginTransaction();
    try {
        Database::run("UPDATE seasons SET started_at = NOW() - INTERVAL 2 DAY - INTERVAL 1 HOUR WHERE status = 'active'");
        $numero = (int) (Database::first("SELECT number FROM seasons WHERE status = 'active' LIMIT 1")['number'] ?? 0);
        $riga = $cronaca($componi());
        Esito::verifica('nel mese della ripartenza racconta la galassia nuova, giorno per giorno',
            $numero !== 1 || (str_starts_with($riga, 'Stagione 1, giorno 3 dalla ripartenza:') && str_contains($riga, 'StarDock')), $riga);
        Database::run("UPDATE seasons SET started_at = NOW() - INTERVAL 31 DAY WHERE status = 'active'");
        Esito::uguale('dopo un mese la notizia sparisce', '', $cronaca($componi()));
    } finally {
        $pdo->rollBack();
    }
    $avvisi = (new ReflectionMethod(\App\Game\FedNews::class, 'avvisiDiServizio'))->invoke(null);
    Esito::verifica('un avviso di servizio spiega cosa azzera una stagione e cosa una ripartenza',
        array_filter($avvisi, static fn ($a) => str_contains($a, 'chiusura di stagione') && str_contains($a, 'ripartenza da zero')
            && str_contains($a, 'albo d\'oro restano')) !== []);
    // Dopo la ripartenza tutti hanno il rating di partenza: il notiziario
    // incoronava «in vetta» il primo a caso. (Dal sorgente: la prova non tocca
    // i comandanti veri per metterli a pari merito.)
    $fednews = (string) file_get_contents($radice . '/src/Game/FedNews.php');
    Esito::verifica('a pari merito nessuno e\' «in vetta»', str_contains($fednews, "(int) \$top['rating'] > \$minimo"));
    Esito::verifica('e nel notiziario «un\'impennata» ha il suo apostrofo',
        str_contains((string) file_get_contents($radice . '/src/Game/Events.php'), "\"un'impennata\""));

    Esito::verifica('dalla console solo con --conferma',
        str_contains((string) file_get_contents($radice . '/bin/console.php'), "case 'revisione:ripartenza':"));
};
