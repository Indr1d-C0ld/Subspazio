<?php
declare(strict_types=1);
/**
 * Lavoratore generico delle prove di concorrenza. Sta fuori dalla suite (il
 * prefisso `_` lo esclude): gira come processo separato, con la sua
 * connessione al database, e parte a un istante comune a tutti.
 *
 * Uso: php tests/_corsa.php <azione> <istante> <argomenti...>
 *   annulla_contratto <playerId> <contractId>
 *   annulla_lavoro    <playerId> <jobId>
 *   compra_hw         <playerId> <item>(0=cloak,1=genesis) <qty>
 *   carica_pianeta    <playerId> <planetId> <qty>
 * Esce con 0 se l'azione e' riuscita, 1 altrimenti.
 */
require dirname(__DIR__) . '/bin/_bootstrap.php';

use App\Core\Database;
use App\Game\Contracts;
use App\Game\Industry;
use App\Game\Planets;
use App\Game\PlayerService;
use App\Game\Shipyard;

[$azione, $t0] = [$argv[1] ?? '', (float) ($argv[2] ?? 0)];
$a = array_map('intval', array_slice($argv, 3));
$player = Database::first('SELECT * FROM players WHERE id = ?', [$a[0] ?? 0]);
while (microtime(true) < $t0) {
    usleep(200);
}
$r = match ($azione) {
    'annulla_contratto' => Contracts::cancel($player, $a[1]),
    'annulla_lavoro'    => Industry::cancelJob($player, $a[1]),
    'compra_hw'         => Shipyard::buyHardware($player, PlayerService::ship((int) $player['ship_id']), $a[1] === 0 ? 'cloak' : 'genesis', $a[2] ?? 1),
    'carica_pianeta'    => Planets::moveResources($player, PlayerService::ship((int) $player['ship_id']), $a[1], 'ore', $a[2], 'load'),
    default             => ['ok' => false],
};
exit(empty($r['ok']) ? 1 : 0);
