<?php
/**
 * POST /api/posts/bulk.php
 * عملیات گروهی روی چند نوشته
 *
 * پارامترها: ids[] و action (trash | restore | force | publish | draft)
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('edit_posts');

$input = getJsonInput();
$ids = array_values(array_unique(array_filter(
    array_map('intval', (array) ($input['ids'] ?? [])),
    fn($id) => $id > 0
)));
$action = (string) ($input['action'] ?? '');

if (empty($ids)) {
    jsonError('هیچ موردی انتخاب نشده است');
}

if (count($ids) > 100) {
    jsonError('حداکثر ۱۰۰ مورد در هر عملیات گروهی مجاز است');
}

if (!in_array($action, ['trash', 'restore', 'force', 'publish', 'draft'], true)) {
    jsonError('عملیات درخواستی نامعتبر است');
}

$affected = 0;
$skipped = 0;

foreach ($ids as $id) {
    $post = getPost($id, false);

    if ($post === null) {
        $skipped++;
        continue;
    }

    // هر مورد جداگانه از نظر دسترسی بررسی می‌شود
    $allowed = in_array($action, ['trash', 'force'], true)
        ? canDeletePost($post)
        : canEditPost($post);

    if (!$allowed) {
        $skipped++;
        continue;
    }

    $done = match ($action) {
        'trash'   => trashPost($id),
        'restore' => restorePost($id),
        'force'   => deletePost($id),
        'publish' => currentUserCan('publish_posts')
            && savePost(['title' => $post['title'], 'content' => $post['content'],
                         'excerpt' => $post['excerpt'], 'slug' => $post['slug'],
                         'type' => $post['type'], 'status' => 'publish',
                         'featured_image' => $post['featured_image'],
                         'comment_status' => $post['comment_status'] === 'open'], $id)['success'],
        'draft'   => savePost(['title' => $post['title'], 'content' => $post['content'],
                         'excerpt' => $post['excerpt'], 'slug' => $post['slug'],
                         'type' => $post['type'], 'status' => 'draft',
                         'featured_image' => $post['featured_image'],
                         'comment_status' => $post['comment_status'] === 'open'], $id)['success'],
    };

    $done ? $affected++ : $skipped++;
}

jsonSuccess(
    ['affected' => $affected, 'skipped' => $skipped],
    $affected > 0
        ? toPersianDigits((string) $affected) . ' مورد پردازش شد'
          . ($skipped > 0 ? ' — ' . toPersianDigits((string) $skipped) . ' مورد نادیده گرفته شد' : '')
        : 'هیچ موردی پردازش نشد'
);
