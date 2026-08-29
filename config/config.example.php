<?php

declare(strict_types=1);

/**
 * MODELLO di configurazione.
 *
 * Copiare in una posizione FUORI dal DocumentRoot e valorizzare i segreti:
 *   /data/subspazio-config/config.php        (default cercato dall'app)
 *   /etc/subspazio/config.php                (consigliato, richiede sudo)
 *
 * L'app cerca, in ordine: $SUBSPAZIO_CONFIG, /etc/subspazio/config.php,
 * /data/subspazio-config/config.php, <progetto>/config/config.php.
 */

return [
    'app' => [
        'name'        => 'SubSpazio',
        'env'         => 'production',   // 'local' per gli errori a schermo
        'debug'       => false,
        'timezone'    => 'Europe/Rome',
        'pretty_urls' => false,          // true solo dopo aver abilitato deploy/apache-subspazio.conf
        'base_path'   => null,           // null = autodetect
    ],

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'tw_subspazio',
        'user'    => 'tw_subspazio',
        'pass'    => 'CAMBIAMI',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'session_name' => 'subspazio_sess',
        'session_ttl'  => 60 * 60 * 8,
    ],

    'paths' => [
        'root' => '/data/html/subspazio',
    ],
];
