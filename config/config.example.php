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

    // Trasporto e-mail (opzionale). 'transport' = 'log' disattiva l'invio reale.
    // Con 'smtp', 'from_email' deve essere un mittente verificato dal provider.
    'mail' => [
        'transport'   => 'log',                  // 'smtp' | 'sendmail' | 'log'
        'smtp_host'   => 'smtp-relay.example.com',
        'smtp_port'   => 587,
        'smtp_secure' => 'tls',
        'smtp_user'   => 'CAMBIAMI',
        'smtp_pass'   => 'CAMBIAMI',
        'from_email'  => 'noreply@example.com',
        'from_name'   => 'SubSpazio',
    ],

    // Notifiche all'amministratore.
    'notify' => [
        'new_registration'      => false,
        'admin_email'           => 'admin@example.com',
        'new_registration_mode' => 'digest',    // 'digest' | 'immediate'
    ],
];
