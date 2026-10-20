<?php
/**
 * QuoteBox installer.
 *
 * Runs exactly once. When config/config.php exists the installer is locked.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once QB_APP . '/schema.php';

qb_session_start();

$configDir = QB_ROOT . '/config';
if (!is_dir($configDir)) {
    @mkdir($configDir, 0755, true);
}

$configWritable = is_dir($configDir) && is_writable($configDir);

$checks = array(
    array(
        'label' => 'PHP 版本 ≥ 7.4',
        'ok' => PHP_VERSION_ID >= 70400,
        'detail' => PHP_VERSION,
    ),
    array(
        'label' => 'PDO MySQL 扩展',
        'ok' => extension_loaded('pdo_mysql'),
        'detail' => extension_loaded('pdo_mysql') ? '已启用' : '未启用',
    ),
    array(
        'label' => 'Session 支持',
        'ok' => function_exists('session_start'),
        'detail' => function_exists('session_start') ? '已启用' : '未启用',
    ),
    array(
        'label' => 'JSON 支持',
        'ok' => function_exists('json_encode'),
        'detail' => function_exists('json_encode') ? '已启用' : '未启用',
    ),
    array(
        'label' => 'config 目录可写',
        'ok' => $configWritable,
        'detail' => $configWritable ? '可写' : '请将 config 目录权限设为可写（755/775）',
    ),
);

$environmentOk = true;
foreach ($checks as $check) {
    if (!$check['ok']) {
        $environmentOk = false;
    }
}

$defaults = array(
    'site_name' => '言匣 QuoteBox',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    'db_prefix' => 'qb_',
    'admin_user' => 'admin',
    'admin_password' => '',
    'admin_password2' => '',
);

$values = $defaults;
$errors = array();
$manualConfig = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !QB_INSTALLED) {
    csrf_verify();

    foreach (array('site_name', 'db_host', 'db_port', 'db_name', 'db_user', 'db_prefix', 'admin_user') as $field) {
        $values[$field] = post_str($field, $defaults[$field]);
    }
    $values['db_password'] = is_string(raw_post('db_password', '')) ? (string) raw_post('db_password', '') : '';
    $values['admin_password'] = is_string(raw_post('admin_password', '')) ? (string) raw_post('admin_password', '') : '';
    $values['admin_password2'] = is_string(raw_post('admin_password2', '')) ? (string) raw_post('admin_password2', '') : '';

    if (!$environmentOk) {
        $errors[] = '服务器环境不满足安装条件，请先处理上方标记为“未通过”的项目。';
    }

    if ($values['site_name'] === '' || qb_strlen($values['site_name']) > 60) {
        $errors[] = '站点名称不能为空，且不超过 60 个字符。';
    }

    if ($values['db_host'] === '') {
        $errors[] = '请填写数据库主机。';
    }

    $dbPort = (int) $values['db_port'];
    if ($dbPort < 1 || $dbPort > 65535) {
        $errors[] = '数据库端口应在 1–65535 之间。';
    }

    if ($values['db_name'] === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $values['db_name'])) {
        $errors[] = '数据库名称不能为空，且只能包含字母、数字、下划线和短横线。';
    }

    if ($values['db_user'] === '') {
        $errors[] = '请填写数据库用户名。';
    }

    if (!preg_match('/^[A-Za-z0-9_]{0,24}$/', $values['db_prefix'])) {
        $errors[] = '表前缀只能包含字母、数字和下划线，最长 24 个字符。';
    }

    if (!preg_match('/^[A-Za-z0-9_\-\.]{3,50}$/', $values['admin_user'])) {
        $errors[] = '管理员用户名需为 3–50 位，只能包含字母、数字、下划线、短横线或点。';
    }

    if (strlen($values['admin_password']) < 8) {
        $errors[] = '管理员密码至少需要 8 位字符。';
    }

    if ($values['admin_password'] !== $values['admin_password2']) {
        $errors[] = '两次输入的管理员密码不一致。';
    }

    // The database password is intentionally kept out of $values and only used here.
    $dbPassword = $values['db_password'];

    if (empty($errors)) {
        try {
            $dbConfig = array(
                'host' => $values['db_host'],
                'port' => $dbPort,
                'database' => $values['db_name'],
                'username' => $values['db_user'],
                'password' => $dbPassword,
                'charset' => 'utf8mb4',
                'prefix' => $values['db_prefix'],
            );

            $database = new Database($dbConfig);

            foreach (qb_schema($values['db_prefix']) as $statement) {
                $database->run($statement);
            }

            $existingAdmins = (int) $database->value('SELECT COUNT(*) FROM {admins}');
            if ($existingAdmins > 0) {
                throw new RuntimeException('该表前缀下已存在管理员账号。为避免覆盖现有数据，请更换表前缀，或清空数据表后重新安装。');
            }

            $now = date('Y-m-d H:i:s');

            $database->insert('admins', array(
                'username' => $values['admin_user'],
                'password_hash' => password_hash($values['admin_password'], PASSWORD_DEFAULT),
                'created_at' => $now,
                'last_login_at' => null,
            ));

            $settings = array(
                'site_name' => $values['site_name'],
                'api_cors' => '*',
                'installed_version' => QB_VERSION,
            );

            foreach ($settings as $key => $value) {
                $database->run(
                    'INSERT INTO {settings} (setting_key, setting_value, updated_at) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = ?',
                    array($key, $value, $now, $value, $now)
                );
            }

            $categoryCount = (int) $database->value('SELECT COUNT(*) FROM {categories}');
            $quoteCount = (int) $database->value('SELECT COUNT(*) FROM {quotes}');

            if ($categoryCount === 0 && $quoteCount === 0) {
                $demoCategories = array(
                    array('诗词', 'poem', '古诗词与诗性句子', 10),
                    array('书籍', 'book', '书摘与文学片段', 20),
                    array('电影', 'movie', '电影台词', 30),
                    array('语录', 'saying', '名人名言与金句', 40),
                    array('代码', 'code', '开发者格言', 50),
                );

                $categoryIds = array();
                foreach ($demoCategories as $item) {
                    $categoryIds[$item[1]] = $database->insert('categories', array(
                        'name' => $item[0],
                        'slug' => $item[1],
                        'description' => $item[2],
                        'sort_order' => $item[3],
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
                }

                $demoQuotes = array(
                    'poem' => array(
                        array('人生若只如初见，何事秋风悲画扇。', '纳兰性德', '木兰花·拟古决绝词柬友'),
                        array('山有木兮木有枝，心悦君兮君不知。', '佚名', '越人歌'),
                        array('长风破浪会有时，直挂云帆济沧海。', '李白', '行路难'),
                        array('落霞与孤鹜齐飞，秋水共长天一色。', '王勃', '滕王阁序'),
                    ),
                    'book' => array(
                        array('生活不能等待别人来安排，要自己去争取和奋斗。', '路遥', '平凡的世界'),
                        array('满地都是六便士，他却抬头看见了月亮。', '毛姆', '月亮与六便士'),
                        array('所有的大人都曾经是小孩，虽然只有少数人记得。', '圣埃克苏佩里', '小王子'),
                    ),
                    'movie' => array(
                        array('希望是美好的，也许是人间至善，而美好的事物永不消逝。', '安迪', '肖申克的救赎'),
                        array('生活就像一盒巧克力，你永远不知道下一颗是什么味道。', '阿甘', '阿甘正传'),
                        array('如果上天能够给我一个再来一次的机会，我会对那个女孩说三个字：我爱你。', '至尊宝', '大话西游'),
                    ),
                    'saying' => array(
                        array('种一棵树最好的时间是十年前，其次是现在。', '非洲谚语', ''),
                        array('世界上只有一种真正的英雄主义，那就是在认清生活的真相后依然热爱生活。', '罗曼·罗兰', '米开朗琪罗传'),
                        array('你的时间有限，不要浪费在重复别人的生活上。', '史蒂夫·乔布斯', '斯坦福演讲'),
                    ),
                    'code' => array(
                        array('程序是写给人读的，只是偶尔让计算机执行一下。', 'Donald Knuth', 'Literate Programming'),
                        array('过早的优化是万恶之源。', 'Donald Knuth', ''),
                        array('简单是可靠的前提。', 'Edsger Dijkstra', ''),
                    ),
                );

                foreach ($demoQuotes as $slug => $items) {
                    foreach ($items as $item) {
                        $database->insert('quotes', array(
                            'category_id' => $categoryIds[$slug],
                            'content' => $item[0],
                            'author' => $item[1],
                            'source' => $item[2],
                            'is_active' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ));
                    }
                }
            }

            $config = array(
                'db' => $dbConfig,
                'app_key' => bin2hex(random_bytes(32)),
                'timezone' => 'Asia/Shanghai',
                'debug' => false,
                'installed_at' => date('c'),
                'version' => QB_VERSION,
            );

            $manualConfig = "<?php\n"
                . "if (count(get_included_files()) === 1) { http_response_code(404); exit; }\n\n"
                . 'return ' . var_export($config, true) . ";\n";

            $configFile = $configDir . '/config.php';
            $written = @file_put_contents($configFile, $manualConfig, LOCK_EX);

            if ($written === false) {
                throw new RuntimeException('数据库已初始化，但配置写入失败。请手动创建 config/config.php 并粘贴页面下方显示的配置内容。');
            }

            @chmod($configFile, 0640);

            flash('success', '安装完成，请使用管理员账号登录。');
            redirect(admin_url('login.php'));
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$esc = function ($value) {
    return e($value);
};
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>安装 · 言匣 QuoteBox</title>
    <link rel="stylesheet" href="<?= e(url('assets/install.css')) ?>?v=<?= e(QB_VERSION) ?>">
</head>
<body>
<div class="install-shell">
    <aside class="brand-panel">
        <div class="brand-mark">言匣</div>
        <p class="brand-en">QUOTEBOX</p>
        <h1>把好句子<br>装进一只匣子。</h1>
        <p class="brand-copy">为个人站点与独立博客准备的随机一言服务。一条接口，一处后台，随取随用。</p>

        <div class="quote-preview">
            <p id="previewText">人生若只如初见，何事秋风悲画扇。</p>
            <span>— 纳兰性德《木兰花》</span>
        </div>

        <ul class="feature-list">
            <li>随机返回，可指定分类</li>
            <li>后台维护分类与文料</li>
            <li>无需 Composer，上传即用</li>
        </ul>
    </aside>

    <main class="form-panel">
        <?php if (QB_INSTALLED): ?>
            <div class="card installed-card">
                <p class="eyebrow">INSTALLED</p>
                <h2>言匣已经安装完成</h2>
                <p class="muted">配置文件已存在，安装程序已自动锁定。若需要全新安装，请先删除 <code>config/config.php</code>（会丢失当前配置，数据库数据不会被自动删除）。</p>
                <div class="installed-actions">
                    <a class="btn btn-primary" href="<?= e(admin_url('login.php')) ?>">进入管理后台</a>
                    <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">查看站点首页</a>
                </div>
                <div class="notice notice-warn">
                    <strong>安全提示</strong>
                    <p>安装完成后建议删除服务器上的 <code>install.php</code>，并确认 <code>config/config.php</code> 无法被直接访问。</p>
                </div>
            </div>
        <?php else: ?>
            <div class="form-head">
                <p class="eyebrow">FIRST RUN</p>
                <h2>安装言匣</h2>
                <p class="muted">填写数据库与管理员信息，安装程序会自动建表并写入配置文件。</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="notice notice-error">
                    <strong>安装未完成</strong>
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($manualConfig !== ''): ?>
                <div class="notice notice-warn">
                    <strong>请手动保存以下配置</strong>
                    <p>保存到 <code>config/config.php</code> 后即可访问后台。</p>
                    <pre><?= e($manualConfig) ?></pre>
                </div>
            <?php endif; ?>

            <div class="checks">
                <?php foreach ($checks as $check): ?>
                    <span class="check <?= $check['ok'] ? 'ok' : 'bad' ?>" title="<?= e($check['detail']) ?>">
                        <span class="check-dot"></span><?= e($check['label']) ?>
                    </span>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?= e(url('install.php')) ?>" autocomplete="off" novalidate>
                <?= csrf_field() ?>

                <section class="form-section">
                    <h3>站点</h3>
                    <label class="field">
                        <span>站点名称</span>
                        <input type="text" name="site_name" maxlength="60" value="<?= $esc($values['site_name']) ?>" placeholder="言匣 QuoteBox" required>
                    </label>
                </section>

                <section class="form-section">
                    <div class="section-title">
                        <h3>数据库</h3>
                        <span>MySQL / MariaDB</span>
                    </div>
                    <p class="hint">请先在虚拟主机面板创建一个空数据库，再填写下面的信息。</p>

                    <div class="grid grid-2">
                        <label class="field">
                            <span>数据库主机</span>
                            <input type="text" name="db_host" value="<?= $esc($values['db_host']) ?>" placeholder="localhost" required>
                        </label>
                        <label class="field">
                            <span>端口</span>
                            <input type="number" name="db_port" min="1" max="65535" value="<?= $esc($values['db_port']) ?>" required>
                        </label>
                    </div>

                    <label class="field">
                        <span>数据库名称</span>
                        <input type="text" name="db_name" value="<?= $esc($values['db_name']) ?>" placeholder="例如 quotebox" required>
                    </label>

                    <div class="grid grid-2">
                        <label class="field">
                            <span>数据库用户名</span>
                            <input type="text" name="db_user" value="<?= $esc($values['db_user']) ?>" required>
                        </label>
                        <label class="field">
                            <span>数据库密码</span>
                            <input type="password" name="db_password" value="" autocomplete="new-password">
                        </label>
                    </div>

                    <label class="field">
                        <span>表前缀</span>
                        <input type="text" name="db_prefix" maxlength="24" value="<?= $esc($values['db_prefix']) ?>" placeholder="qb_">
                        <small>同一数据库内安装多个站点时，请使用不同前缀。</small>
                    </label>
                </section>

                <section class="form-section">
                    <h3>管理员账号</h3>
                    <label class="field">
                        <span>用户名</span>
                        <input type="text" name="admin_user" maxlength="50" value="<?= $esc($values['admin_user']) ?>" required>
                    </label>
                    <div class="grid grid-2">
                        <label class="field">
                            <span>密码</span>
                            <input type="password" name="admin_password" minlength="8" autocomplete="new-password" required>
                            <small>至少 8 位。</small>
                        </label>
                        <label class="field">
                            <span>确认密码</span>
                            <input type="password" name="admin_password2" minlength="8" autocomplete="new-password" required>
                        </label>
                    </div>
                </section>

                <button type="submit" class="btn btn-primary btn-block" <?= $environmentOk ? '' : 'disabled' ?>>
                    开始安装
                </button>
                <p class="form-foot">安装完成后，建议删除 <code>install.php</code>。</p>
            </form>
        <?php endif; ?>
    </main>
</div>
<script>
(function () {
    var quotes = [
        ['人生若只如初见，何事秋风悲画扇。', '— 纳兰性德《木兰花》'],
        ['山有木兮木有枝，心悦君兮君不知。', '— 佚名《越人歌》'],
        ['满地都是六便士，他却抬头看见了月亮。', '— 毛姆《月亮与六便士》'],
        ['希望是美好的，也许是人间至善，而美好的事物永不消逝。', '— 《肖申克的救赎》']
    ];
    var text = document.getElementById('previewText');
    if (!text) return;
    var index = 0;
    setInterval(function () {
        index = (index + 1) % quotes.length;
        text.style.opacity = '0';
        setTimeout(function () {
            text.textContent = quotes[index][0];
            var meta = text.parentNode.querySelector('span');
            if (meta) meta.textContent = quotes[index][1];
            text.style.opacity = '1';
        }, 240);
    }, 5200);
}());
</script>
</body>
</html>
