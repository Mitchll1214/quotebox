<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
Auth::requireLogin();

$categories = db()->all(
    'SELECT c.id, c.name, c.slug, c.description, c.sort_order, c.is_active, c.created_at,
            (SELECT COUNT(*) FROM {quotes} q WHERE q.category_id = c.id) AS quote_count
     FROM {categories} c
     ORDER BY c.sort_order ASC, c.id ASC'
);

$formError = '';
$formValues = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postAction = post_str('action');

    try {
        if ($postAction === 'save') {
            $categoryId = (int) raw_post('id', 0);
            $name = post_str('name');
            $slugInput = post_str('slug');
            $description = post_str('description');
            $sortOrder = (int) raw_post('sort_order', 0);
            $isActive = post_str('is_active') === '1' ? 1 : 0;

            $formValues = array(
                'id' => $categoryId,
                'name' => $name,
                'slug' => $slugInput,
                'description' => $description,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            );

            if ($name === '') {
                throw new RuntimeException('分类名称不能为空。');
            }
            if (qb_strlen($name) > 60) {
                throw new RuntimeException('分类名称不能超过 60 个字符。');
            }
            if (qb_strlen($description) > 255) {
                throw new RuntimeException('分类说明不能超过 255 个字符。');
            }

            $generatedSlug = false;
            if ($slugInput === '') {
                $slug = qb_random_slug();
                $generatedSlug = true;
            } else {
                $slug = normalize_slug($slugInput);
                if ($slug === '') {
                    throw new RuntimeException('分类参数需包含字母或数字，例如 poem、movie。');
                }
            }

            $duplicate = (int) db()->value(
                'SELECT COUNT(*) FROM {categories} WHERE slug = ? AND id <> ?',
                array($slug, $categoryId)
            );

            // Extremely unlikely, but keep generated values collision-free.
            if ($duplicate > 0 && $generatedSlug) {
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $slug = qb_random_slug();
                    $duplicate = (int) db()->value(
                        'SELECT COUNT(*) FROM {categories} WHERE slug = ? AND id <> ?',
                        array($slug, $categoryId)
                    );
                    if ($duplicate === 0) {
                        break;
                    }
                }
            }

            if ($duplicate > 0) {
                throw new RuntimeException('分类参数“' . $slug . '”已被使用，请换一个。');
            }

            $now = date('Y-m-d H:i:s');

            if ($categoryId > 0) {
                $exists = (int) db()->value('SELECT COUNT(*) FROM {categories} WHERE id = ?', array($categoryId));
                if ($exists === 0) {
                    throw new RuntimeException('该分类不存在，可能已被删除。');
                }

                db()->update('categories', array(
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'updated_at' => $now,
                ), 'id = ?', array($categoryId));

                flash('success', '分类已更新。');
            } else {
                db()->insert('categories', array(
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));

                flash('success', '分类已创建，API 参数为 ?c=' . $slug);
            }

            redirect(admin_url('categories.php'));
        }

        if ($postAction === 'delete') {
            $categoryId = (int) raw_post('id', 0);
            if ($categoryId < 1) {
                throw new RuntimeException('参数不正确。');
            }

            $category = db()->one('SELECT id, name FROM {categories} WHERE id = ?', array($categoryId));
            if ($category === null) {
                throw new RuntimeException('该分类不存在，可能已被删除。');
            }

            $now = date('Y-m-d H:i:s');
            $pdo = db()->pdo();
            $pdo->beginTransaction();

            try {
                $affected = db()->run(
                    'UPDATE {quotes} SET category_id = NULL, updated_at = ? WHERE category_id = ?',
                    array($now, $categoryId)
                )->rowCount();

                db()->delete('categories', 'id = ?', array($categoryId));
                $pdo->commit();
            } catch (Throwable $deleteError) {
                $pdo->rollBack();
                throw $deleteError;
            }

            flash('success', '分类“' . $category['name'] . '”已删除' . ($affected > 0 ? '，' . $affected . ' 条文料移到未分类。' : '。'));
            redirect(admin_url('categories.php'));
        }

        throw new RuntimeException('未知操作。');
    } catch (Throwable $e) {
        if ($postAction === 'save') {
            $formError = $e->getMessage();
        } else {
            flash('error', $e->getMessage());
            redirect(admin_url('categories.php'));
        }
    }
}

$editId = $formValues !== null ? (int) $formValues['id'] : (query_str('action') === 'edit' ? query_int('id', 0) : 0);
$isEditing = $editId > 0;

if ($formValues !== null) {
    $form = $formValues;
} elseif ($isEditing) {
    $category = db()->one(
        'SELECT id, name, slug, description, sort_order, is_active FROM {categories} WHERE id = ?',
        array($editId)
    );

    if ($category === null) {
        flash('error', '该分类不存在，可能已被删除。');
        redirect(admin_url('categories.php'));
    }

    $form = array(
        'id' => (int) $category['id'],
        'name' => (string) $category['name'],
        'slug' => (string) $category['slug'],
        'description' => (string) $category['description'],
        'sort_order' => (int) $category['sort_order'],
        'is_active' => (int) $category['is_active'],
    );
} else {
    $form = array(
        'id' => 0,
        'name' => '',
        'slug' => '',
        'description' => '',
        'sort_order' => 0,
        'is_active' => 1,
    );
}

$title = $isEditing ? '编辑分类' : '分类';
$active = 'categories';
require __DIR__ . '/_header.php';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">CATEGORIES</p>
        <h1 class="page-title">分类</h1>
        <p class="page-sub">分类参数（slug）会直接出现在 API 的 <code>?c=</code> 后面。</p>
    </div>
</div>

<?php if ($formError !== ''): ?>
    <div class="alert alert-error"><?= e($formError) ?></div>
<?php endif; ?>

<div class="split-layout">
    <section class="card table-card">
        <div class="card-head">
            <div>
                <h2>全部分类</h2>
                <p>排序值越小越靠前。</p>
            </div>
            <span class="badge badge-cat"><?= count($categories) ?> 个</span>
        </div>

        <?php if (empty($categories)): ?>
            <p class="empty">还没有分类。使用右侧表单创建第一个。</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table category-table">
                    <thead>
                        <tr>
                            <th>分类</th>
                            <th>API 参数</th>
                            <th class="col-num">文料</th>
                            <th class="col-num">排序</th>
                            <th class="col-status">状态</th>
                            <th class="col-act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <strong class="category-name"><?= e($category['name']) ?></strong>
                                    <?php if ($category['description'] !== ''): ?>
                                        <small class="category-desc"><?= e($category['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code class="slug-code">?c=<?= e($category['slug']) ?></code></td>
                                <td class="col-num"><?= (int) $category['quote_count'] ?></td>
                                <td class="col-num"><?= (int) $category['sort_order'] ?></td>
                                <td class="col-status">
                                    <span class="badge <?= $category['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $category['is_active'] ? '启用' : '停用' ?></span>
                                </td>
                                <td class="col-act">
                                    <a class="row-action" href="<?= e(admin_url('categories.php?action=edit&id=' . (int) $category['id'])) ?>">编辑</a>
                                    <button type="submit" form="deleteCategory" name="id" value="<?= (int) $category['id'] ?>" class="row-action danger" data-confirm="删除分类「<?= e($category['name']) ?>」？其下文料会移到未分类，不会被删除。">删除</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <aside class="card form-card sticky-card" id="categoryForm">
        <div class="card-head">
            <div>
                <h2><?= $isEditing ? '编辑分类' : '新建分类' ?></h2>
                <p><?= $isEditing ? '修改后立即生效。' : '给句子一个清晰的归处。' ?></p>
            </div>
            <?php if ($isEditing): ?>
                <a class="link-mini" href="<?= e(admin_url('categories.php')) ?>">新建</a>
            <?php endif; ?>
        </div>

        <form method="post" action="<?= e(admin_url('categories.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

            <label class="field">
                <span>名称 <em>必填</em></span>
                <input type="text" name="name" maxlength="60" value="<?= e($form['name']) ?>" placeholder="例如：诗词" required>
            </label>

            <label class="field">
                <span>分类参数（slug）</span>
                <input type="text" name="slug" maxlength="40" value="<?= e($form['slug']) ?>" placeholder="例如：poem">
                <small>仅限字母、数字、下划线和短横线；留空会自动生成。调用示例：<code>?c=poem</code></small>
            </label>

            <label class="field">
                <span>说明</span>
                <input type="text" name="description" maxlength="255" value="<?= e($form['description']) ?>" placeholder="可选，仅后台显示">
            </label>

            <div class="form-grid">
                <label class="field">
                    <span>排序</span>
                    <input type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>" step="1">
                </label>
                <div class="field">
                    <span>状态</span>
                    <label class="switch-line">
                        <input type="checkbox" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>>
                        <span>启用</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEditing ? '保存修改' : '创建分类' ?></button>
                <?php if ($isEditing): ?>
                    <a class="btn btn-ghost" href="<?= e(admin_url('categories.php')) ?>">取消</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>

<form method="post" action="<?= e(admin_url('categories.php')) ?>" id="deleteCategory">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
</form>
<?php require __DIR__ . '/_footer.php'; ?>
