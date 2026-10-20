<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
Auth::requireLogin();

$totalQuotes = (int) db()->value('SELECT COUNT(*) FROM {quotes}');
$activeQuotes = (int) db()->value('SELECT COUNT(*) FROM {quotes} WHERE is_active = 1');
$totalCategories = (int) db()->value('SELECT COUNT(*) FROM {categories}');
$activeCategories = (int) db()->value('SELECT COUNT(*) FROM {categories} WHERE is_active = 1');

$recent = db()->all(
    'SELECT q.id, q.content, q.author, q.created_at, q.is_active, c.name AS category_name
     FROM {quotes} q LEFT JOIN {categories} c ON c.id = q.category_id
     ORDER BY q.id DESC LIMIT 6'
);

$categoryStats = db()->all(
    'SELECT c.id, c.name, c.slug, c.is_active, COUNT(q.id) AS quote_count
     FROM {categories} c LEFT JOIN {quotes} q ON q.category_id = c.id
     GROUP BY c.id, c.name, c.slug, c.is_active
     ORDER BY c.sort_order ASC, c.id ASC
     LIMIT 8'
);

$firstSlug = db()->value('SELECT slug FROM {categories} WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1');
$apiUrl = (is_https() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost') . url('api.php');

$title = '概览';
$active = 'dashboard';
require __DIR__ . '/_header.php';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">DASHBOARD</p>
        <h1 class="page-title">概览</h1>
        <p class="page-sub">这里是言匣的全部家底。</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(admin_url('quotes.php?action=edit')) ?>">+ 新增文料</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">文料总数</span>
        <strong class="stat-value"><?= $totalQuotes ?></strong>
        <span class="stat-meta">条句子</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">启用中</span>
        <strong class="stat-value"><?= $activeQuotes ?></strong>
        <span class="stat-meta">条可被 API 返回</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">分类</span>
        <strong class="stat-value"><?= $totalCategories ?></strong>
        <span class="stat-meta"><?= $activeCategories ?> 个启用</span>
    </div>
    <div class="stat-card stat-accent">
        <span class="stat-label">接口状态</span>
        <strong class="stat-value stat-live">运行中</strong>
        <span class="stat-meta">随机返回 · 支持分类过滤</span>
    </div>
</div>

<div class="dashboard-grid">
    <section class="card api-card">
        <div class="card-head">
            <div>
                <h2>API 接入</h2>
                <p>把下面的地址放到需要随机一言的地方。</p>
            </div>
            <span class="badge badge-on">LIVE</span>
        </div>

        <div class="api-endpoint">
            <code id="apiEndpoint"><?= e($apiUrl) ?></code>
            <button type="button" class="btn btn-sm btn-ghost" data-copy="<?= e($apiUrl) ?>">复制</button>
        </div>

        <div class="api-examples">
            <div class="api-example">
                <span class="api-example-label">JSON</span>
                <code>GET <?= e($apiUrl) ?></code>
                <button type="button" class="copy-mini" data-copy="<?= e($apiUrl) ?>" aria-label="复制 JSON 地址">复制</button>
            </div>
            <div class="api-example">
                <span class="api-example-label">纯文本</span>
                <code>GET <?= e($apiUrl) ?>?format=text</code>
                <button type="button" class="copy-mini" data-copy="<?= e($apiUrl) ?>?format=text" aria-label="复制纯文本地址">复制</button>
            </div>
            <?php if ($firstSlug): ?>
                <div class="api-example">
                    <span class="api-example-label">指定分类</span>
                    <code>GET <?= e($apiUrl) ?>?c=<?= e($firstSlug) ?></code>
                    <button type="button" class="copy-mini" data-copy="<?= e($apiUrl) ?>?c=<?= e($firstSlug) ?>" aria-label="复制分类地址">复制</button>
                </div>
            <?php endif; ?>
        </div>

        <p class="api-hint">也可以使用 <code>callback=函数名</code> 获得 JSONP 响应；<code>c</code> 支持逗号分隔多个分类。</p>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>分类概览</h2>
                <p>API 参数即分类别名。</p>
            </div>
            <a class="link-mini" href="<?= e(admin_url('categories.php')) ?>">管理</a>
        </div>

        <?php if (empty($categoryStats)): ?>
            <p class="empty">还没有分类，先创建一个吧。</p>
        <?php else: ?>
            <ul class="category-mini-list">
                <?php foreach ($categoryStats as $category): ?>
                    <li>
                        <span class="category-mini-name"><?= e($category['name']) ?></span>
                        <code>?c=<?= e($category['slug']) ?></code>
                        <span class="category-mini-count"><?= (int) $category['quote_count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>最近添加</h2>
            <p>最新入库的句子。</p>
        </div>
        <a class="link-mini" href="<?= e(admin_url('quotes.php')) ?>">全部文料</a>
    </div>

    <?php if (empty($recent)): ?>
        <div class="empty-state">
            <p>文料库还是空的。</p>
            <a class="btn btn-primary" href="<?= e(admin_url('quotes.php?action=edit')) ?>">添加第一条</a>
        </div>
    <?php else: ?>
        <div class="recent-list">
            <?php foreach ($recent as $quote): ?>
                <a class="recent-item" href="<?= e(admin_url('quotes.php?action=edit&id=' . (int) $quote['id'])) ?>">
                    <span class="recent-content"><?= e($quote['content']) ?></span>
                    <span class="recent-meta">
                        <?php if ($quote['category_name'] !== null): ?>
                            <span class="badge badge-cat"><?= e($quote['category_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!$quote['is_active']): ?>
                            <span class="badge badge-off">已停用</span>
                        <?php endif; ?>
                        <span><?= e(format_datetime($quote['created_at'])) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
