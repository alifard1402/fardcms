<?php
/**
 * POST|DELETE /api/posts/delete.php
 * انتقال به زباله‌دان، بازگردانی یا حذف کامل
 *
 * پارامتر action: trash (پیش‌فرض) | restore | force
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('delete_posts');

$input = getJsonInput();
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
$action = (string) ($input['action'] ?? $_GET['action'] ?? 'trash');

if ($id <= 0) {
    jsonError('شناسه نامعتبر است');
}

$post = getPost($id, false);

if ($post === null) {
    jsonError('محتوای مورد نظر یافت نشد', 404);
}

if (!canDeletePost($post)) {
    jsonError('شما اجازه حذف این محتوا را ندارید', 403);
}

$done = match ($action) {
    'restore' => restorePost($id),
    'force'   => deletePost($id),
    default   => trashPost($id),
};

if (!$done) {
    jsonError('انجام این عملیات ممکن نشد');
}

jsonSuccess(['id' => $id], match ($action) {
    'restore' => 'محتوا بازگردانی شد',
    'force'   => 'محتوا برای همیشه حذف شد',
    default   => 'محتوا به زباله‌دان منتقل شد',
});
