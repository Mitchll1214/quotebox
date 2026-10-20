<?php
/**
 * QuoteBox configuration.
 *
 * Copy this file to config/config.php, or simply run install.php once.
 * Never commit the generated config/config.php: it contains database credentials.
 */

if (count(get_included_files()) === 1) {
    http_response_code(404);
    exit;
}

return array(
    'db' => array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'quotebox',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'prefix' => 'qb_',
    ),
    'app_key' => 'change-me-to-a-random-string',
    'timezone' => 'Asia/Shanghai',
    'debug' => false,
    'installed_at' => '',
    'version' => '1.0.0',
);
