<?php
/**
 * POST /api/media/update.php
 * ویرایش متن جایگزین و توضیح یک فایل
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('upload_files');

$input = getJsonInput();
$id = (int) ($input['id'] ?? 0);

$item = $id > 0 ? getMediaItem($id) : null;

if ($item === null) {
    jsonError('فایل مورد نظر یافت نشد', 404);
}

// فقط صاحب فایل یا دارنده دسترسی مدیریت رسانه
if ((int) $item['user_id'] !== currentUserId() && !currentUserCan('manage_media')) {
    jsonError('شما اجازه ویرایش این فایل را ندارید', 403);
}

updateMedia($id, $input);

jsonSuccess(['media' => getMediaItem($id)], 'تغییرات ذخیره شد');
