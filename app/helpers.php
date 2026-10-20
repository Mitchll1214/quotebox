<?php
/**
 * Shared helpers. Deliberately Composer-free for shared hosting.
 */

declare(strict_types=1);

/**
 * HTML escape.
 *
 * @param mixed $value
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Read a config value.
 *
 * @param mixed $default
 * @return mixed
 */
function qb_config(?string $key = null, $default = null)
{
    $config = isset($GLOBALS['qb_config']) && is_array($GLOBALS['qb_config']) ? $GLOBALS['qb_config'] : array();

    if ($key === null) {
        return $config;
    }

    return array_key_exists($key, $config) ? $config[$key] : $default;
}

/**
 * Base path when QuoteBox lives in a subdirectory, e.g. "/quotebox".
 */
function qb_base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $root = realpath(QB_ROOT);
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;

    if ($root !== false && $documentRoot !== false) {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

        if ($documentRoot !== '' && strpos($root . '/', $documentRoot . '/') === 0) {
            $base = rtrim(substr($root, strlen($documentRoot)), '/');

            return $base;
        }
    }

    $script = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
    $dir = rtrim(dirname($script), '/');

    if (defined('QB_ADMIN') && substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }

    $base = ($dir === '.' || $dir === '/') ? '' : $dir;

    return $base;
}

/**
 * Absolute URL path inside the installation, e.g. url('api.php').
 */
function url(string $path = ''): string
{
    return qb_base_path() . '/' . ltrim($path, '/');
}

/**
 * Absolute URL path inside the admin panel.
 */
function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    }

    return false;
}

function redirect(string $location): void
{
    if (!headers_sent()) {
        header('Location: ' . $location, true, 302);
    }

    exit;
}

/**
 * @param mixed $payload
 */
function json_out($payload, int $status = 200, string $contentType = 'application/json; charset=UTF-8'): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: ' . $contentType);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function qb_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $path = qb_base_path();
    if ($path === '') {
        $path = '/';
    }

    session_name('qbsid');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => $path,
        'httponly' => true,
        'secure' => is_https(),
        'samesite' => 'Lax',
    ));
    session_start();
}

function csrf_token(): string
{
    qb_session_start();

    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Abort the request when the CSRF token is missing or wrong.
 */
function csrf_verify(): void
{
    qb_session_start();
    $expected = isset($_SESSION['_csrf']) ? (string) $_SESSION['_csrf'] : '';
    $sent = isset($_POST['_token']) && is_string($_POST['_token']) ? $_POST['_token'] : '';

    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        http_response_code(419);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('页面已过期，请返回并刷新后重试。');
    }
}

/**
 * @param mixed $default
 * @return mixed
 */
function raw_post(string $key, $default = '')
{
    return array_key_exists($key, $_POST) ? $_POST[$key] : $default;
}

function post_str(string $key, string $default = ''): string
{
    $value = raw_post($key, $default);

    return is_string($value) ? trim($value) : $default;
}

/**
 * @param mixed $default
 * @return mixed
 */
function query_raw(string $key, $default = '')
{
    return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

function query_str(string $key, string $default = ''): string
{
    $value = query_raw($key, $default);

    return is_string($value) ? trim($value) : $default;
}

function query_int(string $key, int $default = 0): int
{
    $value = query_raw($key, $default);

    return is_numeric($value) ? (int) $value : $default;
}

function flash(string $type, string $message): void
{
    qb_session_start();
    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = array();
    }
    $_SESSION['_flash'][] = array('type' => $type, 'message' => $message);
}

/**
 * @return array<int, array{type: string, message: string}>
 */
function pull_flashes(): array
{
    qb_session_start();
    $flashes = isset($_SESSION['_flash']) && is_array($_SESSION['_flash']) ? $_SESSION['_flash'] : array();
    unset($_SESSION['_flash']);

    return $flashes;
}

/**
 * All settings as key => value, cached per request.
 *
 * @return array<string, string>
 */
function settings(bool $refresh = false): array
{
    static $cache = null;

    if ($cache === null || $refresh) {
        $cache = array();
        foreach (db()->all('SELECT setting_key, setting_value FROM {settings}') as $row) {
            $cache[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
    }

    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings();

    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function save_setting(string $key, string $value): void
{
    db()->run(
        'INSERT INTO {settings} (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = ?',
        array($key, $value, date('Y-m-d H:i:s'), $value, date('Y-m-d H:i:s'))
    );
    settings(true);
}

/**
 * UTF-8 aware length with a safe fallback for hosts without mbstring.
 */
function qb_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

/**
 * Shorten text for list views.
 */
function qb_excerpt(string $text, int $limit = 90): string
{
    if (qb_strlen($text) <= $limit) {
        return $text;
    }

    $short = function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);

    return $short . '…';
}

/**
 * Human-readable time, used in the admin list.
 */
function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('Y-m-d H:i', $timestamp);
}

/**
 * Normalize a category slug supplied by the admin.
 */
function normalize_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    $slug = (string) preg_replace('/[^a-z0-9_-]+/', '-', $slug);
    $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

    return substr($slug, 0, 40);
}

function qb_random_slug(): string
{
    return 'cat-' . substr(bin2hex(random_bytes(4)), 0, 6);
}

/**
 * Pagination metadata.
 *
 * @return array{page: int, per_page: int, total: int, pages: int, offset: int}
 */
function paginate(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages = (int) ceil($total / $perPage);
    $pages = max(1, $pages);
    $page = min(max(1, $page), $pages);

    return array(
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    );
}

/**
 * Page numbers for pagination, with null marking an ellipsis.
 *
 * @return array<int, int|null>
 */
function pagination_numbers(int $current, int $pages, int $window = 2): array
{
    if ($pages <= 7) {
        return range(1, $pages);
    }

    $numbers = array(1);
    $start = max(2, $current - $window);
    $end = min($pages - 1, $current + $window);

    if ($start > 2) {
        $numbers[] = null;
    }

    for ($i = $start; $i <= $end; $i++) {
        $numbers[] = $i;
    }

    if ($end < $pages - 1) {
        $numbers[] = null;
    }

    $numbers[] = $pages;

    return $numbers;
}
