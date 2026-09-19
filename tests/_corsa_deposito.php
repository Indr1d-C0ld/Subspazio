<?php
declare(strict_types=1);
/**
 * Lavoratore della prova di concorrenza su un deposito (vedi concorrenza.php).
 * Sta fuori dalla suite — il prefisso `_` lo esclude dalla raccolta — perche'
 * gira come processo separato: servono connessioni distinte al database,
 * altrimenti non c'e' nessuna corsa da osservare.
 *
 * Uso: php tests/_corsa_deposito.php <shipId> <playerId> <featureId> <istante>
 */
require dirname(__DIR__) . '/bin/_bootstrap.php';

use App\Core\Database;
use App\Game\PlayerService;
use App\Game\SectorFeatures;

[$sid, $pid, $fid, $t0] = [(int) $argv[1], (int) $argv[2], (int) $argv[3], (float) $argv[4]];

$player = Database::first('SELECT * FROM players WHERE id = ?', [$pid]);
$ship   = PlayerService::ship($sid);
while (microtime(true) < $t0) {                 // barriera: si parte tutti insieme
    usleep(200);
}
$r = SectorFeatures::harvest($player, $ship, $fid);
exit(empty($r['ok']) ? 1 : 0);
