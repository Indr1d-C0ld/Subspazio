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
    Esito::verifica('dalla console solo con --conferma',
        str_contains((string) file_get_contents($radice . '/bin/console.php'), "case 'revisione:ripartenza':"));
};
