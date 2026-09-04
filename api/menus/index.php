<?php
/**
 * GET /api/menus/index.php
 * فهرست‌ها همراه با محتوای قابل افزودن به آن‌ها
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('manage_menus');

jsonSuccess([
    'menus'      => getMenus(),
    'locations'  => menuLocations(),
    'pages'      => getPosts(['type' => 'page', 'status' => 'publish', 'per_page' => 100])['items'],
    'posts'      => getPosts(['type' => 'post', 'status' => 'publish', 'per_page' => 50])['items'],
    'categories' => getTerms('category'),
    'tags'       => getTerms('tag'),
]);
