<?php
/**
 * POST /api/terms/save.php
 * ایجاد یا ویرایش دسته و برچسب
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('manage_categories');

$input = getJsonInput();
$id = !empty($input['id']) ? (int) $input['id'] : null;

if ($id !== null && getTerm($id) === null) {
    jsonError('مورد درخواستی یافت نشد', 404);
}

$result = saveTerm($input, $id);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess($result['data'], $result['message']);
