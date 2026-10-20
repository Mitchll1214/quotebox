<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';
Auth::requireLogin();

$requestedAction = query_str('action');

if ($requestedAction === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="quotebox-import-template.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'wb');
    if ($output !== false) {
        fputcsv($output, array('内容', '作者', '出处'), ',', '"', '\\');
        fclose($output);
    }
    exit;
}

if ($requestedAction === 'export') {
    $exportRows = db()->all(
        'SELECT q.content, q.author, q.source, c.name AS category_name, q.is_active, q.created_at
         FROM {quotes} q
         LEFT JOIN {categories} c ON c.id = q.category_id
         ORDER BY q.id ASC'
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="quotebox-quotes-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'wb');
    if ($output !== false) {
        fputcsv($output, array('内容', '作者', '出处', '分类', '状态', '创建时间'), ',', '"', '\\');

        foreach ($exportRows as $row) {
            fputcsv($output, array(
                (string) $row['content'],
                (string) $row['author'],
                (string) $row['source'],
                $row['category_name'] !== null ? (string) $row['category_name'] : '未分类',
                $row['is_active'] ? '启用' : '停用',
                (string) $row['created_at'],
            ), ',', '"', '\\');
        }

        fclose($output);
    }
    exit;
}

$categories = db()->all('SELECT id, name, slug, is_active FROM {categories} ORDER BY sort_order ASC, id ASC');

$listFilters = array();
foreach (array('q', 'category', 'status') as $key) {
    $value = post_str($key);
    if ($value !== '') {
        $listFilters[$key] = $value;
    }
}
$listPage = (int) raw_post('page', 0);
if ($listPage > 1) {
    $listFilters['page'] = $listPage;
}
$backUrl = admin_url('quotes.php' . (!empty($listFilters) ? '?' . http_build_query($listFilters) : ''));

$formValues = null;
$formError = '';
$action = 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $postAction = post_str('action');

    try {
        if ($postAction === 'save') {
            $quoteId = (int) raw_post('id', 0);
            $categoryId = (int) raw_post('category_id', 0);
            $content = post_str('content');
            $author = post_str('author');
            $source = post_str('source');
            $isActive = post_str('is_active') === '1' ? 1 : 0;

            $formValues = array(
                'id' => $quoteId,
                'content' => $content,
                'author' => $author,
                'source' => $source,
                'category_id' => $categoryId,
                'is_active' => $isActive,
            );

            if ($content === '') {
                throw new RuntimeException('句子内容不能为空。');
            }
            if (qb_strlen($content) > 2000) {
                throw new RuntimeException('句子内容不能超过 2000 个字符。');
            }
            if (qb_strlen($author) > 100) {
                throw new RuntimeException('作者不能超过 100 个字符。');
            }
            if (qb_strlen($source) > 200) {
                throw new RuntimeException('出处不能超过 200 个字符。');
            }

            if ($categoryId > 0) {
                $categoryExists = (int) db()->value('SELECT COUNT(*) FROM {categories} WHERE id = ?', array($categoryId));
                if ($categoryExists === 0) {
                    throw new RuntimeException('所选分类不存在。');
                }
            } else {
                $categoryId = 0;
            }

            $now = date('Y-m-d H:i:s');

            if ($quoteId > 0) {
                $exists = (int) db()->value('SELECT COUNT(*) FROM {quotes} WHERE id = ?', array($quoteId));
                if ($exists === 0) {
                    throw new RuntimeException('这条文料不存在，可能已被删除。');
                }

                db()->update('quotes', array(
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'content' => $content,
                    'author' => $author,
                    'source' => $source,
                    'is_active' => $isActive,
                    'updated_at' => $now,
                ), 'id = ?', array($quoteId));

                flash('success', '文料已更新。');
            } else {
                db()->insert('quotes', array(
                    'category_id' => $categoryId > 0 ? $categoryId : null,
                    'content' => $content,
                    'author' => $author,
                    'source' => $source,
                    'is_active' => $isActive,
                    'created_at' => $now,
                    'updated_at' => $now,
                ));

                flash('success', '文料已添加。');
            }

            redirect($backUrl);
        }

        if ($postAction === 'delete') {
            $quoteId = (int) raw_post('id', 0);
            if ($quoteId < 1) {
                throw new RuntimeException('参数不正确。');
            }

            $deleted = db()->delete('quotes', 'id = ?', array($quoteId));
            flash($deleted > 0 ? 'success' : 'warning', $deleted > 0 ? '已删除 1 条文料。' : '没有找到要删除的文料。');
            redirect($backUrl);
        }

        if ($postAction === 'bulk') {
            $bulkAction = post_str('bulk_action');
            $rawIds = raw_post('ids', array());
            $ids = array();

            if (is_array($rawIds)) {
                foreach ($rawIds as $rawId) {
                    $candidate = (int) $rawId;
                    if ($candidate > 0) {
                        $ids[] = $candidate;
                    }
                }
            }

            $ids = array_values(array_unique($ids));
            $ids = array_slice($ids, 0, 500);

            if (empty($ids)) {
                flash('warning', '请先勾选要操作的文料。');
                redirect($backUrl);
            }

            if (!in_array($bulkAction, array('activate', 'deactivate', 'delete'), true)) {
                throw new RuntimeException('请选择有效的批量操作。');
            }

            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            if ($bulkAction === 'delete') {
                $affected = db()->run('DELETE FROM {quotes} WHERE id IN (' . $placeholders . ')', $ids)->rowCount();
                flash('success', '已删除 ' . $affected . ' 条文料。');
            } else {
                $affected = db()->run(
                    'UPDATE {quotes} SET is_active = ?, updated_at = ? WHERE id IN (' . $placeholders . ')',
                    array_merge(array($bulkAction === 'activate' ? 1 : 0, date('Y-m-d H:i:s')), $ids)
                )->rowCount();
                flash('success', '已' . ($bulkAction === 'activate' ? '启用' : '停用') . ' ' . $affected . ' 条文料。');
            }

            redirect($backUrl);
        }

        if ($postAction === 'import') {
            $importMode = post_str('import_mode', 'text');
            $importCategoryId = (int) raw_post('import_category_id', 0);

            if ($importCategoryId > 0) {
                $categoryExists = (int) db()->value('SELECT COUNT(*) FROM {categories} WHERE id = ?', array($importCategoryId));
                if ($categoryExists === 0) {
                    throw new RuntimeException('所选分类不存在。');
                }
            } else {
                $importCategoryId = 0;
            }

            if ($importMode === 'file') {
                if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file'])) {
                    throw new RuntimeException('请选择要导入的 Excel 或 CSV 文件。');
                }

                $importRows = Spreadsheet::parseUpload($_FILES['import_file'], 2000);
                if (empty($importRows)) {
                    throw new RuntimeException('文件中没有可导入的句子，请检查第一列内容是否为空。');
                }
            } else {
                $importText = raw_post('import_text', '');
                $importText = is_string($importText) ? $importText : '';

                if (trim($importText) === '') {
                    throw new RuntimeException('请粘贴要导入的内容。');
                }

                $importRows = Spreadsheet::parseText($importText, 2000);
                if (empty($importRows)) {
                    throw new RuntimeException('没有解析到可导入的句子，请检查粘贴内容。');
                }
            }

            $now = date('Y-m-d H:i:s');
            $inserted = 0;
            $skipped = 0;

            $pdo = db()->pdo();
            $pdo->beginTransaction();

            try {
                foreach ($importRows as $importRow) {
                    $content = isset($importRow[0]) ? trim((string) $importRow[0]) : '';
                    $author = isset($importRow[1]) ? trim((string) $importRow[1]) : '';
                    $source = isset($importRow[2]) ? trim((string) $importRow[2]) : '';

                    if ($content === '' || qb_strlen($content) > 2000 || qb_strlen($author) > 100 || qb_strlen($source) > 200) {
                        $skipped++;
                        continue;
                    }

                    db()->insert('quotes', array(
                        'category_id' => $importCategoryId > 0 ? $importCategoryId : null,
                        'content' => $content,
                        'author' => $author,
                        'source' => $source,
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ));
                    $inserted++;
                }

                $pdo->commit();
            } catch (Throwable $importError) {
                $pdo->rollBack();
                throw $importError;
            }

            $limitNote = count($importRows) >= 2000 ? '（单次上限为 2000 条，如需导入更多请分批操作）' : '';

            if ($inserted > 0) {
                flash('success', '已导入 ' . $inserted . ' 条文料' . ($skipped > 0 ? '，跳过 ' . $skipped . ' 行。' : '。') . $limitNote);
            } else {
                flash('warning', '没有导入任何内容' . ($skipped > 0 ? '，' . $skipped . ' 行格式不正确。' : '，请检查格式。'));
            }

            redirect($backUrl);
        }

        throw new RuntimeException('未知操作。');
    } catch (Throwable $e) {
        if ($postAction === 'save') {
            $formError = $e->getMessage();
            $action = 'edit';
        } else {
            flash('error', $e->getMessage());
            redirect($backUrl);
        }
    }
}

if (query_str('action') === 'edit') {
    $action = 'edit';
}

if ($action === 'edit') {
    $editId = $formValues !== null ? (int) $formValues['id'] : query_int('id', 0);

    if ($formValues !== null) {
        $editing = $formValues;
    } elseif ($editId > 0) {
        $row = db()->one(
            'SELECT id, category_id, content, author, source, is_active FROM {quotes} WHERE id = ?',
            array($editId)
        );

        if ($row === null) {
            flash('error', '这条文料不存在，可能已被删除。');
            redirect(admin_url('quotes.php'));
        }

        $editing = array(
            'id' => (int) $row['id'],
            'content' => (string) $row['content'],
            'author' => (string) $row['author'],
            'source' => (string) $row['source'],
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : 0,
            'is_active' => (int) $row['is_active'],
        );
    } else {
        $editing = array(
            'id' => 0,
            'content' => '',
            'author' => '',
            'source' => '',
            'category_id' => 0,
            'is_active' => 1,
        );
    }

    $editingId = (int) $editing['id'];
    $title = $editingId > 0 ? '编辑文料' : '新增文料';
    $active = 'quotes';
    require __DIR__ . '/_header.php';
    ?>
    <div class="page-head">
        <div>
            <p class="eyebrow">QUOTE</p>
            <h1 class="page-title"><?= e($title) ?></h1>
            <p class="page-sub">一句话，注出处，归入合适的分类。</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-ghost" href="<?= e(admin_url('quotes.php')) ?>">返回列表</a>
        </div>
    </div>

    <?php if ($formError !== ''): ?>
        <div class="alert alert-error"><?= e($formError) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('quotes.php')) ?>" class="card form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editingId ?>">

        <label class="field">
            <span>句子内容 <em>必填</em></span>
            <textarea name="content" rows="5" maxlength="2000" placeholder="写下一句值得被反复读到的句子……" required><?= e($editing['content']) ?></textarea>
            <small>最多 2000 个字符。</small>
        </label>

        <div class="form-grid">
            <label class="field">
                <span>作者</span>
                <input type="text" name="author" maxlength="100" value="<?= e($editing['author']) ?>" placeholder="例如：纳兰性德">
            </label>
            <label class="field">
                <span>出处</span>
                <input type="text" name="source" maxlength="200" value="<?= e($editing['source']) ?>" placeholder="例如：木兰花·拟古决绝词柬友">
            </label>
        </div>

        <div class="form-grid">
            <label class="field">
                <span>分类</span>
                <select name="category_id">
                    <option value="0">未分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) $editing['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?><?= !$category['is_active'] ? '（已停用）' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>状态</span>
                <span class="switch-line">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $editing['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>启用（参与随机返回）</span>
                </span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $editingId > 0 ? '保存修改' : '添加文料' ?></button>
            <a class="btn btn-ghost" href="<?= e(admin_url('quotes.php')) ?>">取消</a>
        </div>
    </form>
    <?php
    require __DIR__ . '/_footer.php';
    exit;
}

// ---- List view ----

$filterQ = query_str('q');
$filterCategory = query_str('category');
$filterStatus = query_str('status');
$page = max(1, query_int('page', 1));

$where = '1 = 1';
$params = array();

if ($filterQ !== '') {
    $where .= ' AND (q.content LIKE ? OR q.author LIKE ? OR q.source LIKE ?)';
    $like = '%' . $filterQ . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filterCategory !== '' && ctype_digit($filterCategory)) {
    $where .= ' AND q.category_id = ?';
    $params[] = (int) $filterCategory;
}

if ($filterStatus === 'on') {
    $where .= ' AND q.is_active = 1';
} elseif ($filterStatus === 'off') {
    $where .= ' AND q.is_active = 0';
}

$total = (int) db()->value('SELECT COUNT(*) FROM {quotes} q WHERE ' . $where, $params);
$pager = paginate($total, 20, $page);

$quotes = db()->all(
    'SELECT q.id, q.content, q.author, q.source, q.is_active, q.updated_at, c.name AS category_name
     FROM {quotes} q LEFT JOIN {categories} c ON c.id = q.category_id
     WHERE ' . $where . '
     ORDER BY q.id DESC
     LIMIT ' . $pager['per_page'] . ' OFFSET ' . $pager['offset'],
    $params
);

$filterParams = array();
if ($filterQ !== '') {
    $filterParams['q'] = $filterQ;
}
if ($filterCategory !== '') {
    $filterParams['category'] = $filterCategory;
}
if ($filterStatus !== '') {
    $filterParams['status'] = $filterStatus;
}

// Preserve the current filters through bulk actions, deletes and imports.
$listFilters = $filterParams;
if ($page > 1) {
    $listFilters['page'] = $page;
}

$pageUrl = function (int $targetPage) use ($filterParams): string {
    $query = $filterParams;
    if ($targetPage > 1) {
        $query['page'] = $targetPage;
    }

    return admin_url('quotes.php' . (!empty($query) ? '?' . http_build_query($query) : ''));
};

$title = '文料库';
$active = 'quotes';
require __DIR__ . '/_header.php';
?>
<div class="page-head">
    <div>
        <p class="eyebrow">LIBRARY</p>
        <h1 class="page-title">文料库</h1>
        <p class="page-sub">共 <strong><?= $total ?></strong> 条，按添加时间倒序。</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(admin_url('quotes.php?action=export')) ?>" title="UTF-8 CSV，可用 Excel 直接打开">导出 CSV</a>
        <a class="btn btn-primary" href="<?= e(admin_url('quotes.php?action=edit')) ?>">+ 新增文料</a>
    </div>
</div>

<form method="get" action="<?= e(admin_url('quotes.php')) ?>" class="filter-bar card">
    <label class="filter-search">
        <span class="sr-only">搜索</span>
        <input type="search" name="q" value="<?= e($filterQ) ?>" placeholder="搜索内容、作者或出处">
    </label>
    <select name="category" aria-label="按分类筛选">
        <option value="">全部分类</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= (int) $category['id'] ?>" <?= $filterCategory === (string) $category['id'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="status" aria-label="按状态筛选">
        <option value="">全部状态</option>
        <option value="on" <?= $filterStatus === 'on' ? 'selected' : '' ?>>启用中</option>
        <option value="off" <?= $filterStatus === 'off' ? 'selected' : '' ?>>已停用</option>
    </select>
    <button type="submit" class="btn btn-ghost">筛选</button>
    <?php if (!empty($filterParams)): ?>
        <a class="filter-reset" href="<?= e(admin_url('quotes.php')) ?>">清除</a>
    <?php endif; ?>
</form>

<?php if (empty($quotes)): ?>
    <div class="card empty-state">
        <?php if (!empty($filterParams)): ?>
            <p>没有符合条件的文料。</p>
            <a class="btn btn-ghost" href="<?= e(admin_url('quotes.php')) ?>">清除筛选</a>
        <?php else: ?>
            <p>文料库还是空的，添加第一条句子吧。</p>
            <a class="btn btn-primary" href="<?= e(admin_url('quotes.php?action=edit')) ?>">添加文料</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(admin_url('quotes.php')) ?>" id="bulkForm" class="card table-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="bulk">
        <?php foreach ($listFilters as $key => $value): ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>

        <div class="bulk-bar">
            <label class="check-line">
                <input type="checkbox" id="selectAll">
                <span>全选</span>
            </label>
            <div class="bulk-actions">
                <select name="bulk_action" aria-label="批量操作">
                    <option value="">批量操作…</option>
                    <option value="activate">启用</option>
                    <option value="deactivate">停用</option>
                    <option value="delete">删除</option>
                </select>
                <button type="submit" class="btn btn-sm btn-ghost" data-confirm="确定执行所选批量操作？">应用</button>
            </div>
            <span class="bulk-count" id="selectedCount">已选 0 条</span>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="col-check"></th>
                        <th>内容</th>
                        <th class="col-cat">分类</th>
                        <th class="col-meta">作者 / 出处</th>
                        <th class="col-status">状态</th>
                        <th class="col-time">更新</th>
                        <th class="col-act"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td class="col-check">
                                <input type="checkbox" name="ids[]" value="<?= (int) $quote['id'] ?>" class="row-check">
                            </td>
                            <td class="cell-content">
                                <a href="<?= e(admin_url('quotes.php?action=edit&id=' . (int) $quote['id'])) ?>"><?= e(qb_excerpt((string) $quote['content'], 80)) ?></a>
                            </td>
                            <td class="col-cat">
                                <?php if ($quote['category_name'] !== null): ?>
                                    <span class="badge badge-cat"><?= e($quote['category_name']) ?></span>
                                <?php else: ?>
                                    <span class="muted-dash">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-meta">
                                <span><?= e($quote['author'] !== '' ? $quote['author'] : '佚名') ?></span>
                                <?php if ($quote['source'] !== ''): ?>
                                    <small><?= e($quote['source']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="col-status">
                                <span class="badge <?= $quote['is_active'] ? 'badge-on' : 'badge-off' ?>"><?= $quote['is_active'] ? '启用' : '停用' ?></span>
                            </td>
                            <td class="col-time"><?= e(format_datetime($quote['updated_at'])) ?></td>
                            <td class="col-act">
                                <a class="row-action" href="<?= e(admin_url('quotes.php?action=edit&id=' . (int) $quote['id'])) ?>">编辑</a>
                                <button type="submit" form="deleteSingle" name="id" value="<?= (int) $quote['id'] ?>" class="row-action danger" data-confirm="确定删除这条文料？">删除</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>

    <form method="post" action="<?= e(admin_url('quotes.php')) ?>" id="deleteSingle">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <?php foreach ($listFilters as $key => $value): ?>
            <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endforeach; ?>
    </form>

    <?php if ($pager['pages'] > 1): ?>
        <nav class="pagination" aria-label="分页">
            <?php if ($pager['page'] > 1): ?>
                <a href="<?= e($pageUrl($pager['page'] - 1)) ?>">上一页</a>
            <?php else: ?>
                <span class="is-disabled">上一页</span>
            <?php endif; ?>

            <?php foreach (pagination_numbers($pager['page'], $pager['pages']) as $number): ?>
                <?php if ($number === null): ?>
                    <span class="ellipsis">…</span>
                <?php elseif ($number === $pager['page']): ?>
                    <span class="is-current"><?= $number ?></span>
                <?php else: ?>
                    <a href="<?= e($pageUrl($number)) ?>"><?= $number ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pager['page'] < $pager['pages']): ?>
                <a href="<?= e($pageUrl($pager['page'] + 1)) ?>">下一页</a>
            <?php else: ?>
                <span class="is-disabled">下一页</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<details class="card import-card">
    <summary>
        <span>批量导入</span>
        <small>粘贴文本，或上传 Excel / CSV</small>
    </summary>

    <div class="import-grid">
        <form method="post" action="<?= e(admin_url('quotes.php')) ?>" class="import-block">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="import_mode" value="text">
            <?php foreach ($listFilters as $key => $value): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
            <?php endforeach; ?>

            <div class="import-head">
                <h3>粘贴文本</h3>
                <span class="import-badge">TEXT</span>
            </div>
            <p class="import-note">一行一条。如需同时填写作者和出处，用 <code>|</code> 分隔：<code>内容 | 作者 | 出处</code>。以 <code>#</code> 开头的行会被忽略。</p>

            <label class="field">
                <span>导入到分类</span>
                <select name="import_category_id">
                    <option value="0">未分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>粘贴内容</span>
                <textarea name="import_text" rows="9" placeholder="人生若只如初见，何事秋风悲画扇。&#10;山有木兮木有枝，心悦君兮君不知。|佚名|越人歌"></textarea>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" data-confirm="确认导入这些内容？">导入文本</button>
                <span class="import-limit">单次最多 2000 条</span>
            </div>
        </form>

        <form method="post" action="<?= e(admin_url('quotes.php')) ?>" enctype="multipart/form-data" class="import-block">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="import">
            <input type="hidden" name="import_mode" value="file">
            <?php foreach ($listFilters as $key => $value): ?>
                <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
            <?php endforeach; ?>

            <div class="import-head">
                <h3>上传 Excel / CSV</h3>
                <span class="import-badge">FILE</span>
            </div>
            <p class="import-note">第一列内容、第二列作者、第三列出处，列顺序与模板一致。支持 <code>.xlsx</code> 与 <code>.csv</code>，首行表头会自动跳过，CSV 支持 UTF-8 / GBK。</p>

            <label class="field">
                <span>导入到分类</span>
                <select name="import_category_id">
                    <option value="0">未分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>选择文件</span>
                <input type="file" name="import_file" class="file-input" accept=".xlsx,.csv" required>
                <small>最大 5 MB。旧版 <code>.xls</code> 请先在 Excel 中另存为 <code>.xlsx</code> 或 CSV。</small>
            </label>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" data-confirm="确认上传并导入这个文件？">上传并导入</button>
                <a class="link-mini" href="<?= e(admin_url('quotes.php?action=template')) ?>">下载 CSV 模板</a>
            </div>
        </form>
    </div>
</details>
<?php require __DIR__ . '/_footer.php'; ?>
