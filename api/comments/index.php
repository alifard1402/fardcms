<?php
/**
 * GET /api/comments/index.php
 * فهرست دیدگاه‌ها برای بازبینی
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('moderate_comments');

$result = getComments([
    'status'   => $_GET['status'] ?? 'all',
    'search'   => $_GET['search'] ?? '',
    'post_id'  => (int) ($_GET['post_id'] ?? 0),
    'page'     => (int) ($_GET['page'] ?? 1),
    'per_page' => (int) ($_GET['per_page'] ?? 20),
]);

jsonSuccess([
    'comments' => $result['items'],
    'meta'     => $result['meta'],
    'counts'   => getCommentCounts(),
]);
