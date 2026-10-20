<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

if (Auth::check()) {
    redirect(admin_url('index.php'));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = post_str('username');
    $password = is_string(raw_post('password', '')) ? (string) raw_post('password', '') : '';

    if ($username === '' || $password === '') {
        $error = '请输入用户名和密码。';
    } else {
        try {
            $error = Auth::attempt($username, $password);
        } catch (Throwable $e) {
            error_log('[QuoteBox Login] ' . $e->getMessage());
            $error = '服务暂时不可用，请稍后再试。';
        }

        if ($error === null) {
            $next = post_str('next', '');
            redirect(Auth::safeNext($next, admin_url('index.php')));
        }
    }
}

$next = query_str('next', '');
if ($next === '') {
    $next = post_str('next', '');
}

$siteName = QB_INSTALLED ? setting('site_name', '言匣 QuoteBox') : '言匣 QuoteBox';
$loggedOut = query_str('logged_out', '') === '1';
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>登录 · <?= e($siteName) ?></title>
    <link rel="stylesheet" href="<?= e(admin_url('assets/admin.css')) ?>?v=<?= e(QB_VERSION) ?>">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-intro">
        <span class="login-brand">言匣</span>
        <p class="login-eyebrow">QUOTEBOX CONSOLE</p>
        <h1>句子在这里<br>被好好收着。</h1>
        <p class="login-copy">登录后可以维护文料、管理分类，并随时通过 API 把随机一言接入你的站点。</p>
        <div class="login-rule"></div>
        <p class="login-site"><?= e($siteName) ?></p>
    </section>

    <section class="login-panel">
        <div class="login-card">
            <p class="eyebrow">ADMIN</p>
            <h2>登录后台</h2>

            <?php if ($loggedOut): ?>
                <div class="alert alert-success">已退出登录。</div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= e(admin_url('login.php')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">

                <label class="field">
                    <span>用户名</span>
                    <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" autofocus required>
                </label>

                <label class="field">
                    <span>密码</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>

                <button type="submit" class="btn btn-primary btn-block">进入后台</button>
            </form>

            <p class="login-foot">忘记密码？请通过数据库或重新安装重置。</p>
        </div>
    </section>
</main>
</body>
</html>
