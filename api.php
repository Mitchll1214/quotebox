<?php
/**
 * QuoteBox public API.
 *
 * GET /api.php                     -> JSON
 * GET /api.php?format=text         -> plain text
 * GET /api.php?callback=fn         -> JSONP
 * GET /api.php?c=poem,movie        -> filter by category slug(s)
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

/**
 * Apply CORS headers. Falls back to "*" when settings are unavailable.
 */
function api_apply_cors(): void
{
    $allowedOrigin = '*';

    try {
        $allowedOrigin = setting('api_cors', '*');
    } catch (Throwable $e) {
        $allowedOrigin = '*';
    }

    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';

    if ($allowedOrigin === '*') {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '') {
        $allowed = array_filter(array_map('trim', explode(',', $allowedOrigin)));
        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }
}

$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!QB_INSTALLED) {
    header('Access-Control-Allow-Origin: *');
    if ($requestMethod === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    json_out(array(
        'code' => 503,
        'message' => 'QuoteBox 尚未安装，请先完成 install.php。',
        'error' => 'not_installed',
    ), 503);
}

api_apply_cors();

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!in_array($requestMethod, array('GET', 'HEAD'), true)) {
    header('Allow: GET, HEAD, OPTIONS');
    json_out(array(
        'code' => 405,
        'message' => '仅支持 GET 请求。',
        'error' => 'method_not_allowed',
    ), 405);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$callbackRaw = query_str('callback', '');
$callback = '';

if ($callbackRaw !== '') {
    if (!preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*(\.[A-Za-z_$][A-Za-z0-9_$]*)*$/', $callbackRaw) || strlen($callbackRaw) > 64) {
        json_out(array(
            'code' => 400,
            'message' => 'callback 参数不合法。',
            'error' => 'invalid_callback',
        ), 400);
    }
    $callback = $callbackRaw;
}

/**
 * @param array<string, mixed> $payload
 */
function api_respond(array $payload, int $status, string $format, string $callback): void
{
    if ($callback !== '') {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/javascript; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        }
        echo '/**/ ' . $callback . '(' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ');';
        exit;
    }

    if ($format === 'text') {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        if ($status === 200 && isset($payload['data']['content'])) {
            echo (string) $payload['data']['content'];
        } else {
            echo isset($payload['message']) ? (string) $payload['message'] : '';
        }
        exit;
    }

    json_out($payload, $status);
}

/**
 * @return never
 */
function api_error(string $message, int $status, string $error, string $format, string $callback): void
{
    api_respond(array(
        'code' => $status,
        'message' => $message,
        'error' => $error,
    ), $status, $format, $callback);
}

$format = strtolower(query_str('format', query_str('encode', 'json')));
if (in_array($format, array('plain', 'txt'), true)) {
    $format = 'text';
}

if (!in_array($format, array('json', 'text'), true)) {
    api_error('format 参数仅支持 json 或 text。', 400, 'invalid_format', 'json', $callback);
}

try {
    $rawCategories = query_str('c', query_str('category', ''));
    $slugs = array();

    if ($rawCategories !== '') {
        $parts = explode(',', $rawCategories);
        foreach ($parts as $part) {
            $slug = trim($part);
            if ($slug === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $slug)) {
                api_error('分类参数只能包含字母、数字、下划线和短横线。', 400, 'invalid_category', $format, $callback);
            }
            $slugs[] = $slug;
        }

        $slugs = array_values(array_unique($slugs));
        if (count($slugs) > 10) {
            api_error('一次最多指定 10 个分类。', 400, 'too_many_categories', $format, $callback);
        }
    }

    $where = 'q.is_active = 1 AND (q.category_id IS NULL OR c.id IS NULL OR c.is_active = 1)';
    $params = array();

    if (!empty($slugs)) {
        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $found = db()->all(
            'SELECT id, slug FROM {categories} WHERE slug IN (' . $placeholders . ') AND is_active = 1',
            $slugs
        );

        if (count($found) !== count($slugs)) {
            $foundSlugs = array_column($found, 'slug');
            $missing = array_values(array_diff($slugs, $foundSlugs));
            api_error('分类不存在或已停用：' . implode(', ', $missing), 404, 'category_not_found', $format, $callback);
        }

        $categoryIds = array_map('intval', array_column($found, 'id'));
        $idPlaceholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        $where .= ' AND q.category_id IN (' . $idPlaceholders . ')';
        $params = array_merge($params, $categoryIds);
    }

    $count = (int) db()->value(
        'SELECT COUNT(*) FROM {quotes} q LEFT JOIN {categories} c ON c.id = q.category_id WHERE ' . $where,
        $params
    );

    if ($count < 1) {
        api_error('该分类下暂时没有可用的句子。', 404, 'quote_not_found', $format, $callback);
    }

    $offset = random_int(0, $count - 1);

    $row = db()->one(
        'SELECT q.id, q.content, q.author, q.source, q.created_at, c.name AS category_name, c.slug AS category_slug
         FROM {quotes} q
         LEFT JOIN {categories} c ON c.id = q.category_id
         WHERE ' . $where . '
         ORDER BY q.id ASC
         LIMIT 1 OFFSET ' . $offset,
        $params
    );

    if ($row === null) {
        api_error('该分类下暂时没有可用的句子。', 404, 'quote_not_found', $format, $callback);
    }

    $data = array(
        'id' => (int) $row['id'],
        'content' => (string) $row['content'],
        'author' => (string) $row['author'],
        'source' => (string) $row['source'],
        'category' => $row['category_slug'] !== null ? array(
            'name' => (string) $row['category_name'],
            'slug' => (string) $row['category_slug'],
        ) : null,
        'created_at' => (string) $row['created_at'],
    );

    $payload = array(
        'code' => 200,
        'message' => 'success',
        'data' => $data,
    );

    if ($requestMethod === 'HEAD') {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=UTF-8');
        }
        exit;
    }

    api_respond($payload, 200, $format, $callback);
} catch (Throwable $e) {
    error_log('[QuoteBox API] ' . $e->getMessage());
    api_error('服务暂时不可用，请稍后再试。', 500, 'server_error', $format, $callback);
}
