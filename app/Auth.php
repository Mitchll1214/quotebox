<?php
/**
 * Admin authentication (session based).
 */

declare(strict_types=1);

final class Auth
{
    /** @var array<string, mixed>|null|false */
    private static $cachedUser = false;

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$cachedUser !== false) {
            return self::$cachedUser;
        }

        qb_session_start();
        $id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;

        if ($id <= 0) {
            self::$cachedUser = null;

            return null;
        }

        $user = db()->one('SELECT id, username, created_at, last_login_at FROM {admins} WHERE id = ?', array($id));

        if ($user === null) {
            unset($_SESSION['admin_id']);
            self::$cachedUser = null;

            return null;
        }

        self::$cachedUser = $user;

        return $user;
    }

    /**
     * @return string|null Error message, or null on success.
     */
    public static function attempt(string $username, string $password): ?string
    {
        qb_session_start();

        $lockedUntil = isset($_SESSION['_login_locked_until']) ? (int) $_SESSION['_login_locked_until'] : 0;
        if ($lockedUntil > time()) {
            $minutes = (int) ceil(($lockedUntil - time()) / 60);

            return '尝试次数过多，请 ' . $minutes . ' 分钟后再试。';
        }

        $admin = db()->one('SELECT * FROM {admins} WHERE username = ?', array($username));

        if ($admin === null || !password_verify($password, (string) $admin['password_hash'])) {
            $fails = isset($_SESSION['_login_fails']) ? (int) $_SESSION['_login_fails'] : 0;
            $fails++;
            $_SESSION['_login_fails'] = $fails;

            if ($fails >= 5) {
                $_SESSION['_login_locked_until'] = time() + 600;
                $_SESSION['_login_fails'] = 0;

                return '尝试次数过多，请 10 分钟后再试。';
            }

            return '用户名或密码不正确。';
        }

        unset($_SESSION['_login_fails'], $_SESSION['_login_locked_until']);

        if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
            db()->update('admins', array('password_hash' => password_hash($password, PASSWORD_DEFAULT)), 'id = ?', array((int) $admin['id']));
        }

        db()->update('admins', array('last_login_at' => date('Y-m-d H:i:s')), 'id = ?', array((int) $admin['id']));

        self::login((int) $admin['id']);

        return null;
    }

    public static function login(int $adminId): void
    {
        qb_session_start();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $adminId;
        self::$cachedUser = false;
    }

    public static function logout(): void
    {
        qb_session_start();
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        self::$cachedUser = null;
    }

    public static function requireLogin(): void
    {
        if (!QB_INSTALLED) {
            redirect(url('install.php'));
        }

        if (!self::check()) {
            $next = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
            redirect(admin_url('login.php?next=' . rawurlencode($next)));
        }
    }

    /**
     * Only allow redirects that stay inside this installation.
     */
    public static function safeNext(string $next, string $fallback): string
    {
        if ($next === '' || strpos($next, '//') === 0 || strpos($next, '\\') !== false) {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $next)) {
            return $fallback;
        }

        if (strpos($next, '/') !== 0) {
            return $fallback;
        }

        return $next;
    }
}
