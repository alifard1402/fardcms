<?php
/**
 * GET /api/posts/show.php?id=...
 * دریافت یک نوشته برای ویرایش
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('edit_posts');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    jsonError('شناسه نامعتبر است');
}

$post = getPost($id);

if ($post === null) {
    jsonError('محتوای مورد نظر یافت نشد', 404);
}

if (!canEditPost($post)) {
    jsonError('شما اجازه ویرایش این محتوا را ندارید', 403);
}

$post['meta'] = getAllPostMeta($id);

// تصویرهای گالری با اطلاعات کامل برمی‌گردند تا ویرایشگر بتواند بدون
// درخواست دوم پیش‌نمایششان را نشان دهد؛ در ذخیره فقط شناسه‌ها می‌روند.
$post['gallery'] = postGallery($id);

jsonSuccess(['post' => $post]);
