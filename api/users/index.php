<?php
/**
 * GET /api/users/index.php
 * فهرست کاربران (فقط با دسترسی مدیریت کاربران)
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('manage_users');

$result = getUsers([
    'search'    => $_GET['search'] ?? '',
    'role'      => $_GET['role'] ?? '',
    'is_active' => $_GET['is_active'] ?? '',
    'page'      => (int) ($_GET['page'] ?? 1),
    'per_page'  => (int) ($_GET['per_page'] ?? 20),
    'orderby'   => $_GET['orderby'] ?? 'id',
    'order'     => $_GET['order'] ?? 'DESC',
]);

jsonSuccess([
    'users'  => $result['items'],
    'meta'   => $result['meta'],
    'counts' => getUserCounts(),
    'roles'  => array_map(
        fn($key, $role) => ['value' => $key, 'label' => $role['label']],
        array_keys(allRoles()),
        allRoles()
    ),
]);
