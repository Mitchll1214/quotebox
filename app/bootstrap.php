<?php
/**
 * QuoteBox bootstrap
 *
 * Loaded by every entry point. Keeps the environment stable on shared hosts:
 * no Composer, no external dependencies.
 */

declare(strict_types=1);

define('QB_VERSION', '1.0.0');
define('QB_ROOT', dirname(__DIR__));
define('QB_APP', __DIR__);

require_once QB_APP . '/helpers.php';

$configFile = QB_ROOT . '/config/config.php';
define('QB_INSTALLED', is_file($configFile));

$GLOBALS['qb_config'] = QB_INSTALLED ? require $configFile : array();

if (!is_array($GLOBALS['qb_config'])) {
    $GLOBALS['qb_config'] = array();
}

$qbDebug = !empty($GLOBALS['qb_config']['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $qbDebug ? '1' : '0');
ini_set('log_errors', '1');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
}

if (!empty($GLOBALS['qb_config']['timezone'])) {
    date_default_timezone_set((string) $GLOBALS['qb_config']['timezone']);
} else {
    date_default_timezone_set('Asia/Shanghai');
}

require_once QB_APP . '/Database.php';
require_once QB_APP . '/Spreadsheet.php';
require_once QB_APP . '/Auth.php';

/**
 * Shared database connection (lazy).
 */
function db(): Database
{
    static $instance = null;

    if ($instance instanceof Database) {
        return $instance;
    }

    if (!QB_INSTALLED) {
        throw new RuntimeException('QuoteBox 尚未安装，请先访问 install.php。');
    }

    $dbConfig = qb_config('db', array());
    if (!is_array($dbConfig) || empty($dbConfig['database'])) {
        throw new RuntimeException('数据库配置不完整，请检查 config/config.php。');
    }

    $instance = new Database($dbConfig);

    return $instance;
}

/**
 * Render a fatal error page for browser-facing entries.
 */
function qb_fatal(Throwable $e): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $debug = !empty(qb_config('debug'));
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>服务暂时不可用 · QuoteBox</title>';
    echo '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f6f3ec;color:#24211d;font-family:system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif}.box{max-width:640px;padding:40px;background:#fff;border:1px solid #e6dfd2;border-radius:16px;box-shadow:0 20px 50px rgba(60,45,25,.08)}h1{margin:0 0 12px;font-size:22px}p{line-height:1.8;color:#6b6257}code{display:block;white-space:pre-wrap;word-break:break-all;background:#f7f4ee;padding:14px;border-radius:10px;margin-top:16px;font-size:12px;color:#8a3b2c}</style>';
    echo '</head><body><div class="box"><h1>服务暂时不可用</h1>';
    echo '<p>请检查数据库配置，或联系站点管理员。</p>';
    if ($debug) {
        echo '<code>' . e($e->getMessage()) . '</code>';
    }
    echo '</div></body></html>';
    exit;
}
