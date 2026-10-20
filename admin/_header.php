<?php
if (!isset($title)) {
    $title = '管理后台';
}
if (!isset($active)) {
    $active = '';
}

$qbAdminUser = Auth::user();
$qbSiteName = setting('site_name', '言匣 QuoteBox');
$qbFlashes = pull_flashes();

$qbNav = array(
    array('key' => 'dashboard', 'label' => '概览', 'href' => admin_url('index.php')),
    array('key' => 'quotes', 'label' => '文料库', 'href' => admin_url('quotes.php')),
    array('key' => 'categories', 'label' => '分类', 'href' => admin_url('categories.php')),
    array('key' => 'settings', 'label' => '设置', 'href' => admin_url('settings.php')),
);
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title) ?> · <?= e($qbSiteName) ?></title>
    <link rel="stylesheet" href="<?= e(admin_url('assets/admin.css')) ?>?v=<?= e(QB_VERSION) ?>">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="<?= e(admin_url('index.php')) ?>">
            <span class="brand-cn">言匣</span>
            <span class="brand-meta">QUOTEBOX<em>ADMIN</em></span>
        </a>

        <nav class="nav" aria-label="主导航">
            <?php foreach ($qbNav as $item): ?>
                <a class="nav-link <?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= e($item['href']) ?>">
                    <?php if ($item['key'] === 'dashboard'): ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    <?php elseif ($item['key'] === 'quotes'): ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 11h16M4 16h9"/><circle cx="18" cy="17" r="3"/></svg>
                    <?php elseif ($item['key'] === 'categories'): ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 12.2 11.7 4h7.8v7.8l-8.2 8.2a1.4 1.4 0 0 1-2 0l-5.8-5.8a1.4 1.4 0 0 1 0-2Z"/><circle cx="15.5" cy="8" r="1.4"/></svg>
                    <?php else: ?>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.55 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.64 8.9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1Z"/></svg>
                    <?php endif; ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <a class="sidebar-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">查看站点首页<span class="ext">↗</span></a>
            <div class="admin-chip">
                <span class="avatar"><?= e(strtoupper(substr((string) ($qbAdminUser['username'] ?? 'A'), 0, 1))) ?></span>
                <span class="admin-name"><?= e($qbAdminUser['username'] ?? '') ?></span>
            </div>
            <form method="post" action="<?= e(admin_url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="logout-btn">退出登录</button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="menu-toggle" id="menuToggle" aria-label="打开菜单" aria-controls="sidebar" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
            <span class="topbar-title"><?= e($title) ?></span>
            <span class="topbar-site"><?= e($qbSiteName) ?></span>
        </header>

        <main class="content">
            <?php foreach ($qbFlashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>">
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endforeach; ?>
