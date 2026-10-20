<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postAction = post_str('action');

    try {
        if ($postAction === 'general') {
            $siteName = post_str('site_name');
            $cors = post_str('api_cors');

            if ($siteName === '') {
                throw new RuntimeException('站点名称不能为空。');
            }
            if (qb_strlen($siteName) > 60) {
                throw new RuntimeException('站点名称不能超过 60 个字符。');
            }

            if ($cors === '') {
                $cors = '*';
            }

            if ($cors !== '*') {
                $origins = array_values(array_filter(array_map('trim', explode(',', $cors))));
                if (empty($origins)) {
                    throw new RuntimeException('请填写 * 或至少一个允许的来源。');
                }

                foreach ($origins as $origin) {
                    if (!preg_match('#^https?://[^\s,]+$#i', $origin)) {
                        throw new RuntimeException('来源格式不正确：' . $origin . '（需以 http:// 或 https:// 开头）');
                    }
                }

                $cors = implode(',', $origins);
            }

            if (qb_strlen($cors) > 500) {
                throw new RuntimeException('跨域来源列表过长。');
            }

            save_setting('site_name', $siteName);
            save_setting('api_cors', $cors);

            flash('success', '基本设置已保存。');
            redirect(admin_url('settings.php'));
        }

        if ($postAction === 'password') {
            $current = is_string(raw_post('current_password', '')) ? (string) raw_post('current_password', '') : '';
            $newPassword = is_string(raw_post('new_password', '')) ? (string) raw_post('new_password', '') : '';
            $confirm = is_string(raw_post('confirm_password', '')) ? (string) raw_post('confirm_password', '') : '';

            $user = Auth::user();
            if ($user === null) {
                redirect(admin_url('login.php'));
            }

            $admin = db()->one('SELECT id, password_hash FROM {admins} WHERE id = ?', array((int) $user['id']));
            if ($admin === null) {
                throw new RuntimeException('管理员账号不存在。');
            }

            if ($current === '' || !password_verify($current, (string) $admin['password_hash'])) {
                throw new RuntimeException('当前密码不正确。');
            }

            if (qb_strlen($newPassword) < 8) {
                throw new RuntimeException('新密码至少需要 8 位字符。');
            }

            if ($newPassword !== $confirm) {
                throw new RuntimeException('两次输入的新密码不一致。');
            }

            db()->update('admins', array(
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ), 'id = ?', array((int) $admin['id']));

            session_regenerate_id(true);
            flash('success', '密码已更新，请妥善保管。');
            redirect(admin_url('settings.php'));
        }

        throw new RuntimeException('未知操作。');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect(admin_url('settings.php'));
    }
}

$siteName = setting('site_name', '言匣 QuoteBox');
$cors = setting('api_cors', '*');
$dbConfig = qb_config('db', array());
$mysqlVersion = db()->value('SELECT VERSION()');

$apiUrl = (is_https() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost') . url('api.php');
$installedAt = qb_config('installed_at', '');

$title = '设置';
$active = 'settings';
require __DIR__ . '/_header.php';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">SETTINGS</p>
        <h1 class="page-title">设置</h1>
        <p class="page-sub">站点名称、跨域策略与登录密码。</p>
    </div>
</div>

<div class="settings-grid">
    <section class="card form-card">
        <div class="card-head">
            <div>
                <h2>基本设置</h2>
                <p>影响后台与首页的展示，以及 API 的跨域策略。</p>
            </div>
        </div>

        <form method="post" action="<?= e(admin_url('settings.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="general">

            <label class="field">
                <span>站点名称</span>
                <input type="text" name="site_name" maxlength="60" value="<?= e($siteName) ?>" required>
            </label>

            <label class="field">
                <span>API 跨域来源（CORS）</span>
                <input type="text" name="api_cors" maxlength="500" value="<?= e($cors) ?>" placeholder="*">
                <small><code>*</code> 允许所有来源；也可填写逗号分隔的白名单，例如 <code>https://blog.example.com,https://www.example.com</code></small>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </div>
        </form>
    </section>

    <section class="card form-card">
        <div class="card-head">
            <div>
                <h2>登录密码</h2>
                <p>修改后当前会话保持登录，其他设备需要重新登录。</p>
            </div>
        </div>

        <form method="post" action="<?= e(admin_url('settings.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <label class="field">
                <span>当前密码</span>
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>

            <div class="form-grid">
                <label class="field">
                    <span>新密码</span>
                    <input type="password" name="new_password" minlength="8" autocomplete="new-password" required>
                    <small>至少 8 位。</small>
                </label>
                <label class="field">
                    <span>确认新密码</span>
                    <input type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">更新密码</button>
            </div>
        </form>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>接口地址</h2>
            <p>可直接复制到你的前端或脚本中。</p>
        </div>
    </div>

    <div class="api-endpoint">
        <code><?= e($apiUrl) ?></code>
        <button type="button" class="btn btn-sm btn-ghost" data-copy="<?= e($apiUrl) ?>">复制</button>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>系统信息</h2>
            <p>排查问题时可参考。</p>
        </div>
        <span class="badge badge-on"><?= e('v' . QB_VERSION) ?></span>
    </div>

    <dl class="info-list">
        <div>
            <dt>PHP</dt>
            <dd><?= e(PHP_VERSION) ?></dd>
        </div>
        <div>
            <dt>MySQL / MariaDB</dt>
            <dd><?= e((string) $mysqlVersion) ?></dd>
        </div>
        <div>
            <dt>数据库</dt>
            <dd><?= e((string) ($dbConfig['database'] ?? '—')) ?>（表前缀 <code><?= e((string) ($dbConfig['prefix'] ?? 'qb_')) ?></code>）</dd>
        </div>
        <div>
            <dt>安装时间</dt>
            <dd><?= e(format_datetime(is_string($installedAt) ? $installedAt : '')) ?></dd>
        </div>
        <div>
            <dt>配置文件</dt>
            <dd><code>config/config.php</code></dd>
        </div>
    </dl>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
