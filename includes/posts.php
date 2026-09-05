<?php
/**
 * مدیریت محتوا — نوشته‌ها و برگه‌ها
 */

/** انواع محتوای پشتیبانی‌شده */
const POST_TYPES = ['post', 'page'];

/** وضعیت‌های ممکن برای محتوا */
const POST_STATUSES = ['publish', 'draft', 'pending', 'private', 'trash'];

/**
 * برچسب فارسی وضعیت‌ها
 */
function postStatusLabels(): array
{
    return [
        'publish' => 'منتشرشده',
        'draft'   => 'پیش‌نویس',
        'pending' => 'در انتظار بازبینی',
        'private' => 'خصوصی',
        'trash'   => 'زباله‌دان',
    ];
}

/**
 * دریافت فهرست محتوا با فیلتر و صفحه‌بندی
 *
 * @param array{
 *   type?: string, status?: string|string[], author?: int, term?: int, taxonomy?: string,
 *   search?: string, page?: int, per_page?: int, orderby?: string, order?: string,
 *   with_terms?: bool, parent?: int
 * } $args
 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
 */
function getPosts(array $args = []): array
{
    $db = Database::getConnection();

    $type     = pickAllowed($args['type'] ?? null, POST_TYPES, 'post');
    $page     = max(1, (int) ($args['page'] ?? 1));
    $perPage  = min(100, max(1, (int) ($args['per_page'] ?? 10)));
    $offset   = ($page - 1) * $perPage;
    $search   = trim((string) ($args['search'] ?? ''));

    $conditions = ['p.type = :type'];
    $params = ['type' => $type];

    // وضعیت: می‌تواند یک مقدار یا آرایه باشد
    $status = $args['status'] ?? 'publish';

    if ($status !== 'any') {
        $statuses = array_values(array_filter(
            (array) $status,
            fn($s) => in_array($s, POST_STATUSES, true)
        ));

        if (empty($statuses)) {
            $statuses = ['publish'];
        }

        $placeholders = [];
        foreach ($statuses as $i => $s) {
            $placeholders[] = ":status_$i";
            $params["status_$i"] = $s;
        }
        $conditions[] = 'p.status IN (' . implode(', ', $placeholders) . ')';
    } else {
        // «هر وضعیتی» شامل زباله‌دان نمی‌شود
        $conditions[] = "p.status <> 'trash'";
    }

    if (!empty($args['author'])) {
        $conditions[] = 'p.author_id = :author';
        $params['author'] = (int) $args['author'];
    }

    if (isset($args['parent'])) {
        $conditions[] = 'p.parent_id ' . ((int) $args['parent'] === 0 ? 'IS NULL' : '= :parent');
        if ((int) $args['parent'] !== 0) {
            $params['parent'] = (int) $args['parent'];
        }
    }

    if ($search !== '') {
        $conditions[] = likeCondition(['p.title', 'p.content', 'p.excerpt'], $search, $params);
    }

    // فیلتر بر اساس دسته‌بندی یا برچسب
    //
    // به‌جای JOIN از زیرپرسمان استفاده شده است. با JOIN، نوشته‌ای که چند
    // ترم منطبق داشت چند بار برمی‌گشت و برای حذف تکرار GROUP BY لازم
    // می‌شد؛ اما GROUP BY روی p.id در کنار ستون‌های جدول users زیر حالت
    // ONLY_FULL_GROUP_BY (پیش‌فرض MySQL 5.7 و 8) خطای ۱۰۵۵ می‌دهد.
    // این شکل هم بدون تکرار است و هم در هر حالت SQL معتبر می‌ماند.
    if (!empty($args['term'])) {
        $conditions[] = 'p.id IN (SELECT tr.post_id FROM ' . tbl('term_relationships')
            . ' tr WHERE tr.term_id = :term)';
        $params['term'] = (int) $args['term'];
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    // ترتیب — فقط ستون‌های مجاز پذیرفته می‌شوند (جلوگیری از SQL injection)
    $allowedOrderBy = [
        'date'     => 'COALESCE(p.published_at, p.created_at)',
        'modified' => 'p.updated_at',
        'title'    => 'p.title',
        'views'    => 'p.views',
        'order'    => 'p.menu_order',
        'id'       => 'p.id',
    ];
    $orderBy = $allowedOrderBy[$args['orderby'] ?? 'date'] ?? $allowedOrderBy['date'];
    $order = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    // شمارش کل
    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . tbl('posts') . " p $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    // دریافت رکوردها
    $stmt = $db->prepare(
        'SELECT p.id, p.title, p.slug, p.excerpt, p.type, p.status, p.featured_image,
                p.parent_id, p.menu_order, p.template, p.comment_status, p.views,
                p.published_at, p.created_at, p.updated_at,
                u.id AS author_id, u.name AS author_name, u.avatar AS author_avatar,
                (SELECT COUNT(*) FROM ' . tbl('comments') . " c
                 WHERE c.post_id = p.id AND c.status = 'approved') AS comment_count
         FROM " . tbl('posts') . " p
         LEFT JOIN " . tbl('users') . " u ON u.id = p.author_id
         $where
         ORDER BY $orderBy $order, p.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // پیوست کردن دسته‌ها و برچسب‌ها
    if (!empty($args['with_terms']) && !empty($items)) {
        $items = attachTermsToPosts($items);
    }

    return [
        'items' => array_map('formatPostRow', $items),
        'meta'  => paginationMeta($total, $page, $perPage),
    ];
}

/**
 * دریافت یک نوشته با شناسه
 */
function getPost(int $id, bool $withTerms = true): ?array
{
    $db = Database::getConnection();

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.avatar AS author_avatar
         FROM ' . tbl('posts') . ' p
         LEFT JOIN ' . tbl('users') . ' u ON u.id = p.author_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        return null;
    }

    if ($withTerms) {
        $post = attachTermsToPosts([$post])[0];
    }

    return formatPostRow($post);
}

/**
 * دریافت یک نوشته با نامک
 */
function getPostBySlug(string $slug, ?string $type = null, array $statuses = ['publish']): ?array
{
    $db = Database::getConnection();

    $conditions = ['p.slug = :slug'];
    $params = ['slug' => $slug];

    if ($type !== null && in_array($type, POST_TYPES, true)) {
        $conditions[] = 'p.type = :type';
        $params['type'] = $type;
    }

    $placeholders = [];
    foreach (array_values($statuses) as $i => $s) {
        $placeholders[] = ":status_$i";
        $params["status_$i"] = $s;
    }
    $conditions[] = 'p.status IN (' . implode(', ', $placeholders) . ')';

    $stmt = $db->prepare(
        'SELECT p.*, u.name AS author_name, u.avatar AS author_avatar
         FROM ' . tbl('posts') . ' p
         LEFT JOIN ' . tbl('users') . ' u ON u.id = p.author_id
         WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1'
    );
    $stmt->execute($params);
    $post = $stmt->fetch();

    if (!$post) {
        return null;
    }

    return formatPostRow(attachTermsToPosts([$post])[0]);
}

/**
 * ایجاد یا به‌روزرسانی محتوا
 *
 * @param array<string, mixed> $data
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function savePost(array $data, ?int $id = null): array
{
    $db = Database::getConnection();

    $title = trim((string) ($data['title'] ?? ''));
    $content = (string) ($data['content'] ?? '');
    $type = pickAllowed($data['type'] ?? null, POST_TYPES, 'post');
    $status = pickAllowed($data['status'] ?? null, POST_STATUSES, 'draft');

    if ($title === '') {
        return ['success' => false, 'message' => 'عنوان نمی‌تواند خالی باشد'];
    }

    if (mb_strlen($title, 'UTF-8') > 250) {
        return ['success' => false, 'message' => 'عنوان نمی‌تواند بیش از ۲۵۰ کاراکتر باشد'];
    }

    // مشارکت‌کننده اجازه انتشار ندارد
    if (in_array($status, ['publish', 'private'], true) && !currentUserCan('publish_posts')) {
        $status = 'pending';
    }

    $content = sanitizeHtml($content);
    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = makeExcerpt($content);
    }

    // نامک
    $slug = trim((string) ($data['slug'] ?? ''));
    $slug = slugify($slug !== '' ? $slug : $title);
    $slug = uniqueSlug('posts', $slug, $id);

    $fields = [
        'title'          => $title,
        'slug'           => $slug,
        'content'        => $content,
        'excerpt'        => mb_substr($excerpt, 0, 500, 'UTF-8'),
        'type'           => $type,
        'status'         => $status,
        'featured_image' => $data['featured_image'] ?? null,
        'parent_id'      => !empty($data['parent_id']) ? (int) $data['parent_id'] : null,
        'menu_order'     => (int) ($data['menu_order'] ?? 0),
        'template'       => $data['template'] ?? null,
        'comment_status' => !empty($data['comment_status']) ? 'open' : 'closed',
    ];

    // زمان انتشار
    $publishedAt = null;
    if (!empty($data['published_at'])) {
        $timestamp = strtotime((string) $data['published_at']);
        if ($timestamp !== false) {
            $publishedAt = date('Y-m-d H:i:s', $timestamp);
        }
    }

    try {
        $db->beginTransaction();

        if ($id === null) {
            // نوشته جدید
            $fields['author_id'] = currentUserId() ?: null;
            $fields['published_at'] = $status === 'publish'
                ? ($publishedAt ?? date('Y-m-d H:i:s'))
                : $publishedAt;

            $columns = array_keys($fields);
            $sql = 'INSERT INTO ' . tbl('posts') . ' (`' . implode('`, `', $columns) . '`) VALUES ('
                 . implode(', ', array_map(fn($c) => ":$c", $columns)) . ')';

            $db->prepare($sql)->execute($fields);
            $id = (int) $db->lastInsertId();

            logActivity('create_post', $type, $id, 'ایجاد ' . ($type === 'page' ? 'برگه' : 'نوشته') . ': ' . $title);
        } else {
            // ویرایش نوشته موجود
            $existing = $db->prepare('SELECT status, published_at FROM ' . tbl('posts') . ' WHERE id = ? LIMIT 1');
            $existing->execute([$id]);
            $current = $existing->fetch();

            if (!$current) {
                $db->rollBack();
                return ['success' => false, 'message' => 'محتوای مورد نظر یافت نشد'];
            }

            // اگر برای اولین بار منتشر می‌شود، زمان انتشار ثبت می‌شود
            if ($publishedAt !== null) {
                $fields['published_at'] = $publishedAt;
            } elseif ($status === 'publish' && empty($current['published_at'])) {
                $fields['published_at'] = date('Y-m-d H:i:s');
            }

            $sets = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($fields)));
            $db->prepare('UPDATE ' . tbl('posts') . " SET $sets WHERE id = :id")
               ->execute($fields + ['id' => $id]);

            logActivity('update_post', $type, $id, 'ویرایش ' . ($type === 'page' ? 'برگه' : 'نوشته') . ': ' . $title);
        }

        // دسته‌بندی‌ها و برچسب‌ها
        if (array_key_exists('categories', $data) || array_key_exists('tags', $data)) {
            syncPostTerms(
                $id,
                array_map('intval', (array) ($data['categories'] ?? [])),
                (array) ($data['tags'] ?? [])
            );
        }

        // فیلدهای سفارشی
        //
        // فقط کلیدهای شناخته‌شده ذخیره می‌شوند: بدون این محدودیت، هر
        // نویسنده‌ای می‌توانست با یک درخواست، کلیدهای دلخواه در جدول
        // postmeta بنشاند.
        if (!empty($data['meta']) && is_array($data['meta'])) {
            foreach (sanitizePostMeta($data['meta']) as $key => $value) {
                setPostMeta($id, $key, $value);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('savePost failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'خطا در ذخیره محتوا'];
    }

    return [
        'success' => true,
        'message' => 'محتوا با موفقیت ذخیره شد',
        'data'    => ['post' => getPost($id)],
    ];
}

/**
 * تغییر وضعیت یک محتوا
 *
 * فقط وضعیت (و در اولین انتشار، زمان انتشار) را تغییر می‌دهد. عمداً از
 * savePost() استفاده نمی‌شود: آن تابع تمام ستون‌ها را می‌نویسد و اگر
 * فراخوان همه فیلدها را نفرستد، مقادیری مثل برگه والد، ترتیب نمایش و
 * قالب اختصاصی پاک می‌شوند.
 *
 * @return array{success: bool, message: string}
 */
function setPostStatus(int $id, string $status): array
{
    if (!in_array($status, POST_STATUSES, true)) {
        return ['success' => false, 'message' => 'وضعیت درخواستی نامعتبر است'];
    }

    if (in_array($status, ['publish', 'private'], true) && !currentUserCan('publish_posts')) {
        return ['success' => false, 'message' => 'شما اجازه انتشار محتوا را ندارید'];
    }

    $db = Database::getConnection();

    $stmt = $db->prepare('SELECT title, type, status, published_at FROM ' . tbl('posts') . ' WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if (!$post) {
        return ['success' => false, 'message' => 'محتوای مورد نظر یافت نشد'];
    }

    // اگر برای اولین بار منتشر می‌شود، زمان انتشار همین حالا ثبت می‌شود
    if ($status === 'publish' && empty($post['published_at'])) {
        $db->prepare('UPDATE ' . tbl('posts') . ' SET status = ?, published_at = NOW() WHERE id = ?')
           ->execute([$status, $id]);
    } else {
        $db->prepare('UPDATE ' . tbl('posts') . ' SET status = ? WHERE id = ?')
           ->execute([$status, $id]);
    }

    // تعداد نوشته‌های هر دسته به وضعیت انتشار وابسته است
    recountAllTerms();

    logActivity('status_post', $post['type'], $id,
        'تغییر وضعیت به ' . (postStatusLabels()[$status] ?? $status) . ': ' . $post['title']);

    return ['success' => true, 'message' => 'وضعیت محتوا تغییر کرد'];
}

/**
 * انتقال به زباله‌دان
 */
function trashPost(int $id): bool
{
    $stmt = Database::getConnection()->prepare(
        'UPDATE ' . tbl('posts') . " SET status = 'trash' WHERE id = ?"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        logActivity('trash_post', 'post', $id, 'انتقال به زباله‌دان');
        return true;
    }

    return false;
}

/**
 * بازگردانی از زباله‌دان
 */
function restorePost(int $id): bool
{
    $stmt = Database::getConnection()->prepare(
        'UPDATE ' . tbl('posts') . " SET status = 'draft' WHERE id = ? AND status = 'trash'"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        logActivity('restore_post', 'post', $id, 'بازگردانی از زباله‌دان');
        return true;
    }

    return false;
}

/**
 * حذف کامل محتوا همراه با وابستگی‌ها
 */
function deletePost(int $id): bool
{
    $db = Database::getConnection();

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM ' . tbl('term_relationships') . ' WHERE post_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM ' . tbl('postmeta') . ' WHERE post_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM ' . tbl('comments') . ' WHERE post_id = ?')->execute([$id]);

        $stmt = $db->prepare('DELETE FROM ' . tbl('posts') . ' WHERE id = ?');
        $stmt->execute([$id]);
        $deleted = $stmt->rowCount() > 0;

        // فرزندان برگه‌ها بدون والد می‌مانند، نه حذف
        $db->prepare('UPDATE ' . tbl('posts') . ' SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);

        $db->commit();

        if ($deleted) {
            recountAllTerms();
            logActivity('delete_post', 'post', $id, 'حذف کامل محتوا');
        }

        return $deleted;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('deletePost failed: ' . $e->getMessage());

        return false;
    }
}

/**
 * افزودن یک بازدید به شمارنده نوشته
 */
function incrementPostViews(int $id): void
{
    try {
        Database::getConnection()
            ->prepare('UPDATE ' . tbl('posts') . ' SET views = views + 1 WHERE id = ?')
            ->execute([$id]);
    } catch (Throwable $e) {
        error_log('Failed to increment views: ' . $e->getMessage());
    }
}

/**
 * نوشته قبلی یا بعدی (بر اساس زمان انتشار)
 */
function getAdjacentPost(array $post, string $direction = 'next'): ?array
{
    $db = Database::getConnection();

    $operator = $direction === 'next' ? '>' : '<';
    $order = $direction === 'next' ? 'ASC' : 'DESC';
    $date = $post['published_at'] ?? $post['created_at'];

    $stmt = $db->prepare(
        'SELECT id, title, slug, featured_image
         FROM ' . tbl('posts') . "
         WHERE type = 'post' AND status = 'publish'
           AND COALESCE(published_at, created_at) $operator ?
         ORDER BY COALESCE(published_at, created_at) $order
         LIMIT 1"
    );
    $stmt->execute([$date]);

    return $stmt->fetch() ?: null;
}

/**
 * شمارش محتوا بر اساس وضعیت
 *
 * @return array<string, int>
 */
function getPostCounts(string $type = 'post'): array
{
    if (!in_array($type, POST_TYPES, true)) {
        $type = 'post';
    }

    $stmt = Database::getConnection()->prepare(
        'SELECT status, COUNT(*) AS total FROM ' . tbl('posts') . ' WHERE type = ? GROUP BY status'
    );
    $stmt->execute([$type]);

    $counts = array_fill_keys(POST_STATUSES, 0);
    $counts['all'] = 0;

    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['total'];

        if ($row['status'] !== 'trash') {
            $counts['all'] += (int) $row['total'];
        }
    }

    return $counts;
}

// ─── فیلدهای سفارشی (postmeta) ─────────────────────────────

/**
 * خواندن یک فیلد سفارشی
 */
function getPostMeta(int $postId, string $key, mixed $default = null): mixed
{
    $stmt = Database::getConnection()->prepare(
        'SELECT meta_value FROM ' . tbl('postmeta') . ' WHERE post_id = ? AND meta_key = ? LIMIT 1'
    );
    $stmt->execute([$postId, $key]);
    $row = $stmt->fetch();

    if (!$row) {
        return $default;
    }

    $decoded = json_decode((string) $row['meta_value'], true);

    return json_last_error() === JSON_ERROR_NONE ? $decoded : $row['meta_value'];
}

/**
 * ذخیره یک فیلد سفارشی
 */
function setPostMeta(int $postId, string $key, mixed $value): void
{
    $stmt = Database::getConnection()->prepare(
        'INSERT INTO ' . tbl('postmeta') . ' (post_id, meta_key, meta_value)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)'
    );
    $stmt->execute([$postId, $key, json_encode($value, JSON_UNESCAPED_UNICODE)]);
}

/**
 * تمام فیلدهای سفارشی یک نوشته
 *
 * @return array<string, mixed>
 */
function getAllPostMeta(int $postId): array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT meta_key, meta_value FROM ' . tbl('postmeta') . ' WHERE post_id = ?'
    );
    $stmt->execute([$postId]);

    $meta = [];
    foreach ($stmt->fetchAll() as $row) {
        $decoded = json_decode((string) $row['meta_value'], true);
        $meta[$row['meta_key']] = json_last_error() === JSON_ERROR_NONE ? $decoded : $row['meta_value'];
    }

    return $meta;
}

// ─── توابع کمکی داخلی ──────────────────────────────────────

/**
 * افزودن فیلدهای محاسبه‌شده به یک رکورد محتوا
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function formatPostRow(array $post): array
{
    $date = $post['published_at'] ?? $post['created_at'] ?? null;

    $post['id'] = (int) $post['id'];
    $post['views'] = (int) ($post['views'] ?? 0);
    $post['comment_count'] = (int) ($post['comment_count'] ?? 0);
    $post['author_id'] = isset($post['author_id']) ? (int) $post['author_id'] : null;
    $post['parent_id'] = isset($post['parent_id']) ? (int) $post['parent_id'] : null;
    $post['menu_order'] = (int) ($post['menu_order'] ?? 0);
    $post['status_label'] = postStatusLabels()[$post['status']] ?? $post['status'];
    $post['url'] = postUrl($post);
    $post['date_jalali'] = $date ? jalaliDate('j F Y', $date) : '';
    $post['date_relative'] = $date ? timeAgo($date) : '';

    // کلمه رمز و هش داخلی هرگز به خروجی نمی‌رود
    unset($post['password']);

    return $post;
}

/**
 * پیوست کردن دسته‌ها و برچسب‌های تمام نوشته‌ها در یک کوئری
 *
 * @param array<int, array<string, mixed>> $posts
 * @return array<int, array<string, mixed>>
 */
function attachTermsToPosts(array $posts): array
{
    $ids = array_map(fn($p) => (int) $p['id'], $posts);

    if (empty($ids)) {
        return $posts;
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));

    $stmt = Database::getConnection()->prepare(
        'SELECT tr.post_id, t.id, t.name, t.slug, t.taxonomy
         FROM ' . tbl('term_relationships') . ' tr
         INNER JOIN ' . tbl('terms') . " t ON t.id = tr.term_id
         WHERE tr.post_id IN ($placeholders)
         ORDER BY t.name"
    );
    $stmt->execute($ids);

    $grouped = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $row['taxonomy'] === 'category' ? 'categories' : 'tags';
        $grouped[(int) $row['post_id']][$key][] = [
            'id'   => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
        ];
    }

    foreach ($posts as &$post) {
        $postId = (int) $post['id'];
        $post['categories'] = $grouped[$postId]['categories'] ?? [];
        $post['tags'] = $grouped[$postId]['tags'] ?? [];
    }

    return $posts;
}
