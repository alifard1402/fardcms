<?php
/**
 * POST /api/posts/save.php
 * ایجاد یا ویرایش نوشته و برگه
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('edit_posts');

$input = getJsonInput();
$id = !empty($input['id']) ? (int) $input['id'] : null;

// ویرایش: بررسی مالکیت محتوا
if ($id !== null) {
    $existing = getPost($id, false);

    if ($existing === null) {
        jsonError('محتوای مورد نظر یافت نشد', 404);
    }

    if (!canEditPost($existing)) {
        jsonError('شما اجازه ویرایش این محتوا را ندارید', 403);
    }
}

$result = savePost($input, $id);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess($result['data'], $result['message']);
