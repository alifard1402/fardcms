<?php
/**
 * ثبت رویدادها (Audit Log)
 */

/**
 * ثبت یک رویداد در گزارش فعالیت‌ها
 */
function logActivity(string $action, string $objectType = '', int $objectId = 0, string $description = ''): void
{
    try {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO ' . tbl('activity_log') . '
             (user_id, action, object_type, object_id, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            currentUserId() ?: null,
            $action,
            $objectType,
            $objectId ?: null,
            mb_substr($description, 0, 255, 'UTF-8'),
            getClientIp(),
        ]);
    } catch (Throwable $e) {
        // ثبت گزارش نباید هرگز جریان اصلی برنامه را متوقف کند
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * دریافت گزارش فعالیت‌ها با صفحه‌بندی
 *
 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
 */
function getActivityLog(int $page = 1, int $perPage = 30, ?int $userId = null): array
{
    $db = Database::getConnection();

    $page = max(1, $page);
    $perPage = min(100, max(1, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = '';
    $params = [];

    if ($userId !== null) {
        $where = 'WHERE a.user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . tbl('activity_log') . " a $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        'SELECT a.id, a.action, a.object_type, a.object_id, a.description,
                a.ip_address, a.created_at, u.name AS user_name
         FROM ' . tbl('activity_log') . ' a
         LEFT JOIN ' . tbl('users') . " u ON u.id = a.user_id
         $where
         ORDER BY a.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(),
        'meta'  => paginationMeta($total, $page, $perPage),
    ];
}

/**
 * حذف گزارش‌های قدیمی‌تر از تعداد روز مشخص
 */
function pruneActivityLog(int $days = 90): int
{
    $stmt = Database::getConnection()->prepare(
        'DELETE FROM ' . tbl('activity_log') . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$days]);

    return $stmt->rowCount();
}
