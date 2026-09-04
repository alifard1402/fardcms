<?php
/**
 * GET /api/media/index.php
 * فهرست کتابخانه رسانه
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('upload_files');

$args = [
    'page'     => (int) ($_GET['page'] ?? 1),
    'per_page' => (int) ($_GET['per_page'] ?? 24),
    'search'   => $_GET['search'] ?? '',
    'type'     => $_GET['type'] ?? '',
];

// بدون دسترسی مدیریت رسانه، کاربر فقط فایل‌های خودش را می‌بیند
if (!currentUserCan('manage_media')) {
    $args['user_id'] = currentUserId();
}

$result = getMediaList($args);

jsonSuccess(['media' => $result['items'], 'meta' => $result['meta']]);
