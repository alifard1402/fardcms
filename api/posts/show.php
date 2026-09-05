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

// افزونه‌ها می‌توانند داده آماده‌ی خود را به پاسخ اضافه کنند تا ویرایشگر
// برای نمایششان درخواست دومی نفرستد
$post = applyFilters('post_edit_payload', $post, $id);

jsonSuccess(['post' => $post]);
