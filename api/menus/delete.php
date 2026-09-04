<?php
/**
 * POST|DELETE /api/menus/delete.php
 * حذف یک فهرست
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('manage_menus');

$input = getJsonInput();
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    jsonError('شناسه نامعتبر است');
}

if (!deleteMenu($id)) {
    jsonError('فهرست مورد نظر یافت نشد', 404);
}

logActivity('delete_menu', 'menu', $id, 'حذف فهرست');

jsonSuccess(['menus' => getMenus()], 'فهرست حذف شد');
