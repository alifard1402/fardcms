<?php
/**
 * GET /api/dashboard/activity.php
 * گزارش کامل فعالیت‌ها با صفحه‌بندی
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('view_activity');

$result = getActivityLog(
    (int) ($_GET['page'] ?? 1),
    (int) ($_GET['per_page'] ?? 30),
    !empty($_GET['user_id']) ? (int) $_GET['user_id'] : null
);

jsonSuccess([
    'activity' => array_map(function (array $row): array {
        $row['date_jalali'] = jalaliDate('j F Y — H:i', $row['created_at']);
        $row['date_relative'] = timeAgo($row['created_at']);

        return $row;
    }, $result['items']),
    'meta' => $result['meta'],
]);
