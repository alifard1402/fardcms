<?php
/**
 * GET /api/posts/index.php
 * فهرست نوشته‌ها یا برگه‌ها (پنل مدیریت)
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('edit_posts');

$type = (string) ($_GET['type'] ?? 'post');

$args = [
    'type'       => $type,
    'status'     => $_GET['status'] ?? 'any',
    'search'     => $_GET['search'] ?? '',
    'page'       => (int) ($_GET['page'] ?? 1),
    'per_page'   => (int) ($_GET['per_page'] ?? 20),
    'orderby'    => $_GET['orderby'] ?? 'date',
    'order'      => $_GET['order'] ?? 'DESC',
    'with_terms' => true,
];

if (!empty($_GET['term'])) {
    $args['term'] = (int) $_GET['term'];
}

// نویسنده‌ها و مشارکت‌کننده‌ها فقط محتوای خودشان را می‌بینند
if (!currentUserCan('edit_others_posts')) {
    $args['author'] = currentUserId();
} elseif (!empty($_GET['author'])) {
    $args['author'] = (int) $_GET['author'];
}

$result = getPosts($args);

jsonSuccess([
    'posts'  => $result['items'],
    'meta'   => $result['meta'],
    'counts' => getPostCounts($type),
]);
