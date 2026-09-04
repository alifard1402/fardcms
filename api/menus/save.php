<?php
/**
 * POST /api/menus/save.php
 * ایجاد یا ویرایش یک فهرست و آیتم‌هایش
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('manage_menus');

$input = getJsonInput();
$id = !empty($input['id']) ? (int) $input['id'] : null;

$result = saveMenu($input, $id);

if (!$result['success']) {
    jsonError($result['message']);
}

$menuId = (int) $result['data']['menu_id'];

// آیتم‌ها در صورت ارسال، کامل جایگزین می‌شوند
if (array_key_exists('items', $input) && is_array($input['items'])) {
    $itemsResult = replaceMenuItems($menuId, $input['items']);

    if (!$itemsResult['success']) {
        jsonError($itemsResult['message']);
    }
}

jsonSuccess(['menus' => getMenus(), 'menu_id' => $menuId], 'فهرست ذخیره شد');
