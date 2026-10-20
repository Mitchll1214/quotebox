<?php
/**
 * QuoteBox landing page.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!QB_INSTALLED) {
    redirect(url('install.php'));
}

try {
    $siteName = setting('site_name', '言匣 QuoteBox');
    $quoteCount = (int) db()->value('SELECT COUNT(*) FROM {quotes} WHERE is_active = 1');
    $categories = db()->all(
        'SELECT c.id, c.name, c.slug, c.description, COUNT(q.id) AS quote_count
         FROM {categories} c
         LEFT JOIN {quotes} q ON q.category_id = c.id AND q.is_active = 1
         WHERE c.is_active = 1
         GROUP BY c.id, c.name, c.slug, c.description, c.sort_order
         ORDER BY c.sort_order ASC, c.id ASC'
    );
} catch (Throwable $e) {
    qb_fatal($e);
}

$apiUrl = (is_https() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost') . url('api.php');
$categoryCount = count($categories);
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="言匣 QuoteBox —— 轻量随机一言服务。随机返回句子，支持分类筛选，适配个人站点与虚拟主机。">
    <title><?= e($siteName) ?> · 随机一言服务</title>
    <link rel="icon" href="<?= e(url('assets/favicon.ico')) ?>?v=<?= e(QB_VERSION) ?>" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/favicon.svg')) ?>?v=<?= e(QB_VERSION) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/apple-touch-icon.png')) ?>?v=<?= e(QB_VERSION) ?>">
    <meta name="theme-color" content="#A6402D">
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>?v=<?= e(QB_VERSION) ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark">言匣</span>
            <span class="brand-sub">QUOTEBOX</span>
        </a>
        <nav class="site-nav" aria-label="页面导航">
            <a href="#demo">试一试</a>
            <a href="#api">接口</a>
            <a href="#categories">分类</a>
        </nav>
        <a class="admin-entry" href="<?= e(admin_url('login.php')) ?>">管理后台</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="wrap hero-grid" id="demo" data-api="<?= e(url('api.php')) ?>">
            <div class="hero-copy">
                <p class="eyebrow">A RANDOM QUOTE SERVICE</p>
                <h1>随机一言，<br><span>从匣子里取出。</span></h1>
                <p class="hero-lead">一个足够轻的句子接口。把好句子收进分类，让它们在需要的时候随机出现。</p>

                <div class="hero-actions">
                    <a class="btn btn-primary" href="#api">查看接口用法</a>
                    <a class="btn btn-quiet" href="<?= e(admin_url('login.php')) ?>">进入后台</a>
                </div>

                <dl class="hero-stats">
                    <div>
                        <dt>可用句子</dt>
                        <dd><?= $quoteCount ?></dd>
                    </div>
                    <div>
                        <dt>分类</dt>
                        <dd><?= $categoryCount ?></dd>
                    </div>
                    <div>
                        <dt>部署</dt>
                        <dd class="stat-word">拖放即用</dd>
                    </div>
                </dl>
            </div>

            <div class="demo-wrap">
                <div class="demo-card" aria-live="polite">
                    <div class="demo-head">
                        <span class="demo-tag"><span class="dot"></span> 随机一言</span>
                        <label class="demo-filter">
                            <span class="sr-only">选择分类</span>
                            <select id="demoCategory">
                                <option value="">全部分类</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category['slug']) ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <blockquote class="demo-quote" id="quoteContent">正在从匣子里取出一句……</blockquote>
                    <p class="demo-meta">
                        <span id="quoteAuthor"></span>
                        <span id="quoteSource"></span>
                    </p>

                    <div class="demo-foot">
                        <span class="demo-category" id="quoteCategory">—</span>
                        <button type="button" class="refresh-btn" id="refreshQuote">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.34 5.66"/><path d="M20 4v7h-7"/></svg>
                            换一句
                        </button>
                    </div>
                </div>
                <div class="demo-endpoint">
                    <code><?= e($apiUrl) ?></code>
                    <button type="button" class="copy-btn" data-copy="<?= e($apiUrl) ?>">复制接口地址</button>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="api">
        <div class="wrap">
            <div class="section-head">
                <p class="eyebrow">API</p>
                <h2>一条接口，四种取法。</h2>
                <p>无需密钥，无需额外配置。默认返回 JSON，也可以直接取纯文本或使用 JSONP。</p>
            </div>

            <div class="doc-layout">
                <div class="doc-list">
                    <article class="doc-item">
                        <span class="doc-index">01</span>
                        <div>
                            <h3>随机获取（JSON）</h3>
                            <code class="doc-code">GET <?= e(url('api.php')) ?></code>
                            <p>返回句子、作者、出处与所属分类。</p>
                        </div>
                    </article>
                    <article class="doc-item">
                        <span class="doc-index">02</span>
                        <div>
                            <h3>纯文本</h3>
                            <code class="doc-code">GET <?= e(url('api.php')) ?>?format=text</code>
                            <p>直接输出句子正文，适合简单嵌入与脚本调用。</p>
                        </div>
                    </article>
                    <article class="doc-item">
                        <span class="doc-index">03</span>
                        <div>
                            <h3>指定分类</h3>
                            <code class="doc-code">GET <?= e(url('api.php')) ?>?c=poem</code>
                            <p>使用后台维护的分类参数；多个分类用逗号分隔：<code>?c=poem,movie</code>。</p>
                        </div>
                    </article>
                    <article class="doc-item">
                        <span class="doc-index">04</span>
                        <div>
                            <h3>JSONP 跨域</h3>
                            <code class="doc-code">GET <?= e(url('api.php')) ?>?callback=show</code>
                            <p>适合无法配置 CORS 的老页面，响应会包裹为 <code>show({...})</code>。</p>
                        </div>
                    </article>
                </div>

                <aside class="response-card">
                    <div class="response-head">
                        <span>RESPONSE</span>
                        <span class="response-status">200 OK</span>
                    </div>
                    <pre><code>{
  "code": 200,
  "message": "success",
  "data": {
    "id": 1,
    "content": "人生若只如初见，何事秋风悲画扇。",
    "author": "纳兰性德",
    "source": "木兰花·拟古决绝词柬友",
    "category": {
      "name": "诗词",
      "slug": "poem"
    },
    "created_at": "2026-01-01 00:00:00"
  }
}</code></pre>
                    <p class="response-note">未找到分类或句子时返回对应 HTTP 状态码与 <code>error</code> 字段。</p>
                </aside>
            </div>
        </div>
    </section>

    <section class="section section-alt" id="categories">
        <div class="wrap">
            <div class="section-head">
                <p class="eyebrow">CATEGORIES</p>
                <h2>匣子里的分格。</h2>
                <p>在后台自由增删分类。每个分类都有一个参数，直接拼在 <code>?c=</code> 后面。</p>
            </div>

            <?php if (empty($categories)): ?>
                <div class="category-empty">
                    <p>还没有启用的分类。</p>
                    <a class="btn btn-primary" href="<?= e(admin_url('categories.php')) ?>">去后台创建</a>
                </div>
            <?php else: ?>
                <div class="category-grid">
                    <?php foreach ($categories as $index => $category): ?>
                        <article class="category-card">
                            <div class="category-top">
                                <span class="category-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                                <span class="category-count"><?= (int) $category['quote_count'] ?> 句</span>
                            </div>
                            <h3><?= e($category['name']) ?></h3>
                            <p><?= e($category['description'] !== '' ? $category['description'] : '等待被句子填满。') ?></p>
                            <div class="category-call">
                                <code>?c=<?= e($category['slug']) ?></code>
                                <button type="button" class="copy-mini" data-copy="<?= e($apiUrl) ?>?c=<?= e($category['slug']) ?>">复制地址</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <div class="wrap closing-card">
            <div>
                <p class="eyebrow">GET STARTED</p>
                <h2>把这句随机，放进你的页面。</h2>
                <p>一行 <code>&lt;script&gt;</code>，或一次简单的 GET 请求。</p>
            </div>
            <div class="closing-actions">
                <button type="button" class="btn btn-primary" data-copy="<?= e($apiUrl) ?>?format=text">复制纯文本接口</button>
                <a class="btn btn-quiet" href="<?= e(admin_url('login.php')) ?>">维护文料库</a>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="wrap footer-inner">
        <span class="footer-credit">© <?= date('Y') ?> <a href="https://github.com/Mitchll1214" target="_blank" rel="noopener noreferrer">Mitchll1214</a> · <?= e($siteName) ?> · 保留所有权利</span>
        <span class="footer-meta">v<?= e(QB_VERSION) ?> · <a href="<?= e(admin_url('login.php')) ?>">管理后台</a></span>
    </div>
</footer>

<script>
(function () {
    'use strict';

    var demo = document.getElementById('demo');
    if (!demo) return;

    var api = demo.getAttribute('data-api');
    var contentEl = document.getElementById('quoteContent');
    var authorEl = document.getElementById('quoteAuthor');
    var sourceEl = document.getElementById('quoteSource');
    var categoryEl = document.getElementById('quoteCategory');
    var refreshBtn = document.getElementById('refreshQuote');
    var categorySelect = document.getElementById('demoCategory');
    var loading = false;

    function loadQuote() {
        if (loading) return;
        loading = true;

        var selected = categorySelect ? categorySelect.value : '';
        var url = api + (selected ? '?c=' + encodeURIComponent(selected) : '');
        contentEl.style.opacity = '0.35';

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (body) {
                    if (!response.ok || !body || !body.data) {
                        throw new Error(body && body.message ? body.message : '暂时没有可用的句子。');
                    }
                    return body.data;
                });
            })
            .then(function (data) {
                contentEl.textContent = data.content;
                authorEl.textContent = data.author ? '— ' + data.author : '— 佚名';
                sourceEl.textContent = data.source ? '《' + data.source + '》' : '';
                categoryEl.textContent = data.category ? data.category.name : '未分类';
                contentEl.style.opacity = '1';
            })
            .catch(function (error) {
                contentEl.textContent = error.message || '匣子暂时空了，请稍后再试。';
                authorEl.textContent = '';
                sourceEl.textContent = '';
                categoryEl.textContent = '—';
                contentEl.style.opacity = '1';
            })
            .then(function () {
                loading = false;
            });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadQuote);
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', loadQuote);
    }

    loadQuote();

    function legacyCopy(text) {
        var area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', 'readonly');
        area.style.position = 'fixed';
        area.style.top = '-1000px';
        document.body.appendChild(area);
        area.select();
        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (error) {
            ok = false;
        }
        document.body.removeChild(area);
        return ok;
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || typeof target.closest !== 'function') return;

        var button = target.closest('[data-copy]');
        if (!button) return;

        var text = button.getAttribute('data-copy');
        var original = button.textContent;

        function done() {
            button.textContent = '已复制';
            window.setTimeout(function () {
                button.textContent = original;
            }, 1600);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                if (legacyCopy(text)) done();
            });
        } else if (legacyCopy(text)) {
            done();
        }
    });
}());
</script>
</body>
</html>
