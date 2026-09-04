<?php
/**
 * POST|DELETE /api/media/delete.php
 * حذف یک فایل از کتابخانه رسانه
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('upload_files');

$input = getJsonInput();
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);

$item = $id > 0 ? getMediaItem($id) : null;

if ($item === null) {
    jsonError('فایل مورد نظر یافت نشد', 404);
}

if ((int) $item['user_id'] !== currentUserId() && !currentUserCan('manage_media')) {
    jsonError('شما اجازه حذف این فایل را ندارید', 403);
}

if (!deleteMedia($id)) {
    jsonError('حذف فایل ممکن نشد');
}

jsonSuccess(['id' => $id], 'فایل حذف شد');
