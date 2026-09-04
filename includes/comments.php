<?php
/**
 * دیدگاه‌ها — ثبت، مدیریت و بازبینی
 */

/** وضعیت‌های ممکن دیدگاه */
const COMMENT_STATUSES = ['approved', 'pending', 'spam', 'trash'];

/**
 * برچسب فارسی وضعیت دیدگاه‌ها
 */
function commentStatusLabels(): array
{
    return [
        'approved' => 'تأییدشده',
        'pending'  => 'در انتظار تأیید',
        'spam'     => 'هرزنامه',
        'trash'    => 'زباله‌دان',
    ];
}

/**
 * ثبت یک دیدگاه جدید از بازدیدکننده
 *
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function addComment(array $data): array
{
    $db = Database::getConnection();

    $postId = (int) ($data['post_id'] ?? 0);
    $content = trim((string) ($data['content'] ?? ''));

    // نوشته باید وجود داشته باشد و دیدگاه‌هایش باز باشد
    $stmt = $db->prepare(
        'SELECT id, title, comment_status FROM ' . tbl('posts') . "
         WHERE id = ? AND status = 'publish' LIMIT 1"
    );
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        return ['success' => false, 'message' => 'نوشته مورد نظر یافت نشد'];
    }

    if ($post['comment_status'] !== 'open' || !getOption('allow_comments', true)) {
        return ['success' => false, 'message' => 'ارسال دیدگاه برای این نوشته بسته است'];
    }

    if (mb_strlen($content, 'UTF-8') < 3) {
        return ['success' => false, 'message' => 'متن دیدگاه باید حداقل ۳ کاراکتر باشد'];
    }

    if (mb_strlen($content, 'UTF-8') > 3000) {
        return ['success' => false, 'message' => 'متن دیدگاه بیش از حد طولانی است'];
    }

    // اطلاعات نویسنده: از حساب کاربری یا فرم مهمان
    $user = getCurrentUser();

    if ($user !== null) {
        $authorName = $user['name'];
        $authorEmail = $user['email'];
        $userId = $user['id'];
    } else {
        $authorName = trim((string) ($data['author_name'] ?? ''));
        $authorEmail = trim((string) ($data['author_email'] ?? ''));
        $userId = null;

        if (mb_strlen($authorName, 'UTF-8') < 2) {
            return ['success' => false, 'message' => 'نام باید حداقل ۲ کاراکتر باشد'];
        }

        if (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'فرمت ایمیل نامعتبر است'];
        }
    }

    // دیدگاه والد باید به همان نوشته تعلق داشته باشد
    $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;

    if ($parentId !== null) {
        $stmt = $db->prepare(
            'SELECT id FROM ' . tbl('comments') . " WHERE id = ? AND post_id = ? AND status = 'approved' LIMIT 1"
        );
        $stmt->execute([$parentId, $postId]);

        if (!$stmt->fetch()) {
            $parentId = null;
        }
    }

    // جلوگیری از ارسال تکراری و پی‌درپی
    if (isDuplicateComment($postId, $authorEmail, $content)) {
        return ['success' => false, 'message' => 'این دیدگاه قبلاً ثبت شده است'];
    }

    // دیدگاه کاربران دارای دسترسی بازبینی مستقیماً تأیید می‌شود
    $status = 'approved';
    if (getOption('moderate_comments', true) && !currentUserCan('moderate_comments')) {
        $status = 'pending';
    }

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('comments') . '
         (post_id, user_id, parent_id, author_name, author_email, content, status, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $postId,
        $userId,
        $parentId,
        mb_substr($authorName, 0, 100, 'UTF-8'),
        mb_substr($authorEmail, 0, 255, 'UTF-8'),
        $content,
        $status,
        getClientIp(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    $id = (int) $db->lastInsertId();

    return [
        'success' => true,
        'message' => $status === 'approved'
            ? 'دیدگاه شما ثبت شد'
            : 'دیدگاه شما ثبت شد و پس از تأیید نمایش داده می‌شود',
        'data'    => ['comment_id' => $id, 'status' => $status],
    ];
}

/**
 * دریافت فهرست دیدگاه‌ها (پنل مدیریت)
 *
 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
 */
function getComments(array $args = []): array
{
    $db = Database::getConnection();

    $page = max(1, (int) ($args['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($args['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];

    $status = $args['status'] ?? 'all';
    if (in_array($status, COMMENT_STATUSES, true)) {
        $conditions[] = 'c.status = :status';
        $params['status'] = $status;
    } else {
        $conditions[] = "c.status <> 'trash'";
    }

    if (!empty($args['post_id'])) {
        $conditions[] = 'c.post_id = :post_id';
        $params['post_id'] = (int) $args['post_id'];
    }

    if (!empty($args['search'])) {
        $conditions[] = likeCondition(
            ['c.content', 'c.author_name', 'c.author_email'],
            trim((string) $args['search']),
            $params
        );
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . tbl('comments') . " c $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        'SELECT c.*, p.title AS post_title, p.slug AS post_slug, p.type AS post_type
         FROM ' . tbl('comments') . ' c
         LEFT JOIN ' . tbl('posts') . " p ON p.id = c.post_id
         $where
         ORDER BY c.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return [
        'items' => array_map('formatCommentRow', $stmt->fetchAll()),
        'meta'  => paginationMeta($total, $page, $perPage),
    ];
}

/**
 * دیدگاه‌های تأییدشده یک نوشته به صورت درختی
 *
 * @return array<int, array<string, mixed>>
 */
function getPostComments(int $postId): array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT c.id, c.parent_id, c.author_name, c.content, c.created_at, u.avatar AS author_avatar
         FROM ' . tbl('comments') . ' c
         LEFT JOIN ' . tbl('users') . " u ON u.id = c.user_id
         WHERE c.post_id = ? AND c.status = 'approved'
         ORDER BY c.id ASC"
    );
    $stmt->execute([$postId]);

    $comments = array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['date_jalali'] = jalaliDate('j F Y', $row['created_at']);
        $row['date_relative'] = timeAgo($row['created_at']);

        return $row;
    }, $stmt->fetchAll());

    return buildCommentTree($comments);
}

/**
 * تغییر وضعیت یک دیدگاه
 */
function setCommentStatus(int $id, string $status): bool
{
    if (!in_array($status, COMMENT_STATUSES, true)) {
        return false;
    }

    $stmt = Database::getConnection()->prepare(
        'UPDATE ' . tbl('comments') . ' SET status = ? WHERE id = ?'
    );
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() > 0) {
        logActivity('comment_' . $status, 'comment', $id, 'تغییر وضعیت دیدگاه به ' . commentStatusLabels()[$status]);
        return true;
    }

    return false;
}

/**
 * ویرایش متن یک دیدگاه
 */
function updateCommentContent(int $id, string $content): bool
{
    $content = trim($content);

    if (mb_strlen($content, 'UTF-8') < 3 || mb_strlen($content, 'UTF-8') > 3000) {
        return false;
    }

    $stmt = Database::getConnection()->prepare(
        'UPDATE ' . tbl('comments') . ' SET content = ? WHERE id = ?'
    );
    $stmt->execute([$content, $id]);

    return $stmt->rowCount() >= 0;
}

/**
 * حذف کامل یک دیدگاه و پاسخ‌هایش
 */
function deleteComment(int $id): bool
{
    $db = Database::getConnection();

    // پاسخ‌ها به والد بالاتر منتقل می‌شوند تا آویزان نمانند
    $db->prepare('UPDATE ' . tbl('comments') . ' SET parent_id = NULL WHERE parent_id = ?')->execute([$id]);

    $stmt = $db->prepare('DELETE FROM ' . tbl('comments') . ' WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        logActivity('delete_comment', 'comment', $id, 'حذف دیدگاه');
        return true;
    }

    return false;
}

/**
 * شمارش دیدگاه‌ها بر اساس وضعیت
 *
 * @return array<string, int>
 */
function getCommentCounts(): array
{
    $rows = Database::getConnection()
        ->query('SELECT status, COUNT(*) AS total FROM ' . tbl('comments') . ' GROUP BY status')
        ->fetchAll();

    $counts = array_fill_keys(COMMENT_STATUSES, 0);
    $counts['all'] = 0;

    foreach ($rows as $row) {
        $counts[$row['status']] = (int) $row['total'];

        if ($row['status'] !== 'trash') {
            $counts['all'] += (int) $row['total'];
        }
    }

    return $counts;
}

// ─── توابع کمکی داخلی ──────────────────────────────────────

/**
 * آیا همین دیدگاه به‌تازگی ثبت شده است؟
 */
function isDuplicateComment(int $postId, string $email, string $content): bool
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id FROM ' . tbl('comments') . '
         WHERE post_id = ? AND author_email = ? AND content = ?
           AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
         LIMIT 1'
    );
    $stmt->execute([$postId, $email, $content]);

    return (bool) $stmt->fetch();
}

/**
 * افزودن فیلدهای محاسبه‌شده به رکورد دیدگاه
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function formatCommentRow(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['post_id'] = (int) $row['post_id'];
    $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
    $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;
    $row['status_label'] = commentStatusLabels()[$row['status']] ?? $row['status'];
    $row['excerpt'] = makeExcerpt($row['content'], 120);
    $row['date_jalali'] = jalaliDate('j F Y — H:i', $row['created_at']);
    $row['date_relative'] = timeAgo($row['created_at']);

    unset($row['user_agent']);

    return $row;
}

/**
 * ساخت درخت پاسخ‌ها
 *
 * @param array<int, array<string, mixed>> $comments
 * @return array<int, array<string, mixed>>
 */
function buildCommentTree(array $comments, ?int $parentId = null): array
{
    $branch = [];

    foreach ($comments as $comment) {
        if ($comment['parent_id'] === $parentId) {
            $children = buildCommentTree($comments, $comment['id']);
            $comment['replies'] = $children;
            $branch[] = $comment;
        }
    }

    return $branch;
}
