<?php
/**
 * POST|DELETE /api/users/delete.php
 * حذف کاربر با امکان انتقال محتوای او به کاربر دیگر
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('manage_users');

$input = getJsonInput();
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
$reassignTo = (int) ($input['reassign_to'] ?? $_GET['reassign_to'] ?? 0);

if ($id <= 0) {
    jsonError('شناسه نامعتبر است');
}

$result = deleteUser($id, $reassignTo);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess(['id' => $id], $result['message']);
