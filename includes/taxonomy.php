<?php
/**
 * دسته‌بندی‌ها و برچسب‌ها (Taxonomy)
 */

/** طبقه‌بندی‌های پشتیبانی‌شده */
const TAXONOMIES = ['category', 'tag'];

/**
 * دریافت فهرست ترم‌ها
 *
 * @return array<int, array<string, mixed>>
 */
function getTerms(string $taxonomy = 'category', array $args = []): array
{
    if (!in_array($taxonomy, TAXONOMIES, true)) {
        return [];
    }

    $db = Database::getConnection();

    $conditions = ['t.taxonomy = :taxonomy'];
    $params = ['taxonomy' => $taxonomy];

    if (!empty($args['search'])) {
        $conditions[] = 't.name LIKE :search';
        $params['search'] = '%' . trim((string) $args['search']) . '%';
    }

    if (!empty($args['hide_empty'])) {
        $conditions[] = 't.count > 0';
    }

    $orderMap = ['name' => 't.name', 'count' => 't.count', 'id' => 't.id'];
    $orderBy = $orderMap[$args['orderby'] ?? 'name'] ?? 't.name';
    $order = strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

    $stmt = $db->prepare(
        'SELECT t.id, t.name, t.slug, t.taxonomy, t.description, t.parent_id, t.count
         FROM ' . tbl('terms') . ' t
         WHERE ' . implode(' AND ', $conditions) . "
         ORDER BY $orderBy $order"
    );
    $stmt->execute($params);

    return array_map(function (array $term): array {
        $term['id'] = (int) $term['id'];
        $term['count'] = (int) $term['count'];
        $term['parent_id'] = $term['parent_id'] !== null ? (int) $term['parent_id'] : null;
        $term['url'] = siteUrl(($term['taxonomy'] === 'category' ? 'category/' : 'tag/') . $term['slug']);

        return $term;
    }, $stmt->fetchAll());
}

/**
 * دریافت یک ترم با شناسه
 */
function getTerm(int $id): ?array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, slug, taxonomy, description, parent_id, count
         FROM ' . tbl('terms') . ' WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);

    return $stmt->fetch() ?: null;
}

/**
 * دریافت یک ترم با نامک
 */
function getTermBySlug(string $slug, string $taxonomy): ?array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, slug, taxonomy, description, parent_id, count
         FROM ' . tbl('terms') . ' WHERE slug = ? AND taxonomy = ? LIMIT 1'
    );
    $stmt->execute([$slug, $taxonomy]);
    $term = $stmt->fetch();

    if (!$term) {
        return null;
    }

    $term['id'] = (int) $term['id'];
    $term['count'] = (int) $term['count'];

    return $term;
}

/**
 * ایجاد یا به‌روزرسانی یک ترم
 *
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function saveTerm(array $data, ?int $id = null): array
{
    $db = Database::getConnection();

    $name = trim((string) ($data['name'] ?? ''));
    $taxonomy = pickAllowed($data['taxonomy'] ?? null, TAXONOMIES, 'category');

    if ($name === '') {
        return ['success' => false, 'message' => 'نام نمی‌تواند خالی باشد'];
    }

    if (mb_strlen($name, 'UTF-8') > 100) {
        return ['success' => false, 'message' => 'نام نمی‌تواند بیش از ۱۰۰ کاراکتر باشد'];
    }

    $slug = trim((string) ($data['slug'] ?? ''));
    $slug = slugify($slug !== '' ? $slug : $name);
    $slug = uniqueSlug('terms', $slug, $id, ['taxonomy' => $taxonomy]);

    $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

    // جلوگیری از حلقه در سلسله‌مراتب دسته‌ها
    if ($id !== null && $parentId !== null && wouldCreateTermCycle($id, $parentId)) {
        return ['success' => false, 'message' => 'دسته والد نمی‌تواند زیرمجموعه خودش باشد'];
    }

    $fields = [
        'name'        => $name,
        'slug'        => $slug,
        'taxonomy'    => $taxonomy,
        'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 500, 'UTF-8'),
        'parent_id'   => $parentId,
    ];

    try {
        if ($id === null) {
            $columns = array_keys($fields);
            $db->prepare(
                'INSERT INTO ' . tbl('terms') . ' (`' . implode('`, `', $columns) . '`) VALUES ('
                . implode(', ', array_map(fn($c) => ":$c", $columns)) . ')'
            )->execute($fields);

            $id = (int) $db->lastInsertId();
            logActivity('create_term', $taxonomy, $id, 'ایجاد ' . ($taxonomy === 'tag' ? 'برچسب' : 'دسته') . ': ' . $name);
        } else {
            $sets = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($fields)));
            $db->prepare('UPDATE ' . tbl('terms') . " SET $sets WHERE id = :id")
               ->execute($fields + ['id' => $id]);

            logActivity('update_term', $taxonomy, $id, 'ویرایش: ' . $name);
        }
    } catch (Throwable $e) {
        error_log('saveTerm failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'خطا در ذخیره'];
    }

    return [
        'success' => true,
        'message' => 'با موفقیت ذخیره شد',
        'data'    => ['term' => getTerm($id)],
    ];
}

/**
 * حذف یک ترم
 */
function deleteTerm(int $id): bool
{
    $db = Database::getConnection();

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM ' . tbl('term_relationships') . ' WHERE term_id = ?')->execute([$id]);

        // زیرمجموعه‌ها به سطح بالا منتقل می‌شوند
        $db->prepare('UPDATE ' . tbl('terms') . ' SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);

        $stmt = $db->prepare('DELETE FROM ' . tbl('terms') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $deleted = $stmt->rowCount() > 0;

        $db->commit();

        if ($deleted) {
            logActivity('delete_term', 'term', $id, 'حذف دسته/برچسب');
        }

        return $deleted;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('deleteTerm failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * همگام‌سازی دسته‌ها و برچسب‌های یک نوشته
 *
 * @param int[]    $categoryIds شناسه دسته‌های موجود
 * @param string[] $tagNames    نام برچسب‌ها (در صورت نبود، ساخته می‌شوند)
 */
function syncPostTerms(int $postId, array $categoryIds, array $tagNames): void
{
    $db = Database::getConnection();

    $termIds = [];

    // دسته‌ها: فقط شناسه‌های موجود پذیرفته می‌شوند
    $categoryIds = array_values(array_unique(array_filter($categoryIds, fn($v) => $v > 0)));

    if (!empty($categoryIds)) {
        $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));
        $stmt = $db->prepare(
            'SELECT id FROM ' . tbl('terms') . " WHERE taxonomy = 'category' AND id IN ($placeholders)"
        );
        $stmt->execute($categoryIds);

        foreach ($stmt->fetchAll() as $row) {
            $termIds[] = (int) $row['id'];
        }
    }

    // برچسب‌ها: در صورت نبود ساخته می‌شوند
    foreach ($tagNames as $tagName) {
        $tagName = trim((string) $tagName);

        if ($tagName === '' || mb_strlen($tagName, 'UTF-8') > 100) {
            continue;
        }

        $termIds[] = findOrCreateTag($tagName);
    }

    // به‌روزرسانی رابطه‌ها
    $db->prepare('DELETE FROM ' . tbl('term_relationships') . ' WHERE post_id = ?')->execute([$postId]);

    if (!empty($termIds)) {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO ' . tbl('term_relationships') . ' (post_id, term_id) VALUES (?, ?)'
        );

        foreach (array_unique($termIds) as $termId) {
            $stmt->execute([$postId, $termId]);
        }
    }

    recountAllTerms();
}

/**
 * یافتن یک برچسب یا ساخت آن
 */
function findOrCreateTag(string $name): int
{
    $db = Database::getConnection();
    $slug = slugify($name);

    $stmt = $db->prepare(
        'SELECT id FROM ' . tbl('terms') . " WHERE taxonomy = 'tag' AND (slug = ? OR name = ?) LIMIT 1"
    );
    $stmt->execute([$slug, $name]);

    if ($existing = $stmt->fetch()) {
        return (int) $existing['id'];
    }

    $db->prepare(
        'INSERT INTO ' . tbl('terms') . " (name, slug, taxonomy) VALUES (?, ?, 'tag')"
    )->execute([$name, uniqueSlug('terms', $slug, null, ['taxonomy' => 'tag'])]);

    return (int) $db->lastInsertId();
}

/**
 * بازشماری تعداد نوشته‌های منتشرشده هر ترم
 */
function recountAllTerms(): void
{
    try {
        Database::getConnection()->exec(
            'UPDATE ' . tbl('terms') . ' t
             SET t.count = (
                SELECT COUNT(*)
                FROM ' . tbl('term_relationships') . ' tr
                INNER JOIN ' . tbl('posts') . " p ON p.id = tr.post_id
                WHERE tr.term_id = t.id AND p.status = 'publish'
             )"
        );
    } catch (Throwable $e) {
        error_log('recountAllTerms failed: ' . $e->getMessage());
    }
}

/**
 * ساخت درخت سلسله‌مراتبی از دسته‌ها
 *
 * @param array<int, array<string, mixed>> $terms
 * @return array<int, array<string, mixed>>
 */
function buildTermTree(array $terms, ?int $parentId = null): array
{
    $branch = [];

    foreach ($terms as $term) {
        if (($term['parent_id'] ?? null) === $parentId) {
            $children = buildTermTree($terms, (int) $term['id']);

            if (!empty($children)) {
                $term['children'] = $children;
            }

            $branch[] = $term;
        }
    }

    return $branch;
}

/**
 * آیا انتخاب این والد باعث ایجاد حلقه می‌شود؟
 */
function wouldCreateTermCycle(int $termId, int $parentId): bool
{
    if ($termId === $parentId) {
        return true;
    }

    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT parent_id FROM ' . tbl('terms') . ' WHERE id = ? LIMIT 1');

    $current = $parentId;
    $guard = 0;

    // پیمایش به سمت بالا؛ محدودیت عمق از حلقه بی‌پایان جلوگیری می‌کند
    while ($current !== null && $guard++ < 100) {
        if ($current === $termId) {
            return true;
        }

        $stmt->execute([$current]);
        $row = $stmt->fetch();
        $current = $row && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    }

    return false;
}
