<?php
/**
 * POST /api/users/save.php
 * ایجاد یا ویرایش کاربر
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('manage_users');

$input = getJsonInput();
$id = !empty($input['id']) ? (int) $input['id'] : null;

$result = $id === null ? createUser($input) : updateUser($id, $input);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess($result['data'], $result['message']);
