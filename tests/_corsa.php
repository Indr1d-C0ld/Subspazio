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
 *   evento_lento      <playerId> <ms>  (avviso scritto in una transazione che
 *                                       resta aperta <ms> millisecondi)
 *   attacca_npc       <playerId> <npcId>
 *   potenzia          <playerId> <itemId>
 *   compra_nave       <playerId> <0=merchant_freighter,1=cargo_transport>
 *   effetto_pendente  <playerId>         (consuma un «free_warp»)
 *   arresto           <playerId>
 * Esce con 0 se l'azione e' riuscita, 1 altrimenti.
 */
require dirname(__DIR__) . '/bin/_bootstrap.php';

use App\Core\Database;
use App\Game\Contracts;
use App\Game\Industry;
use App\Game\Live;
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
    'evento_lento'      => (static function () use ($a): array {
        Database::pdo()->beginTransaction();
        Live::player($a[0], 'destroyed', 'Evento lento', 'scritto dentro una transazione');
        usleep(($a[1] ?? 0) * 1000);
        Database::pdo()->commit();
        return ['ok' => true];
    })(),
    'attacca_npc'       => \App\Game\Combat::attackNpc($player, PlayerService::ship((int) $player['ship_id']), $a[1]),
    'potenzia'          => \App\Game\Modules::upgrade($player, $a[1]),
    'compra_nave'       => Shipyard::buyShip($player, PlayerService::ship((int) $player['ship_id']), ['merchant_freighter', 'cargo_transport'][$a[1]] ?? ''),
    'effetto_pendente'  => ['ok' => \App\Game\Crew::consumePending($a[0], 'free_warp') !== null],
    'arresto'           => (static function () use ($a): array {
        \App\Game\Legge::arresto($a[0]);
        return ['ok' => true];
    })(),
    default             => ['ok' => false],
};
exit(empty($r['ok']) ? 1 : 0);
