<?php
/**
 * POST /api/auth/register.php
 * ثبت‌نام کاربر جدید
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);

if (!getOption('allow_registration', true)) {
    jsonError('ثبت‌نام در این سایت غیرفعال است', 403);
}

$input = getJsonInput();

$result = authRegister(
    trim((string) ($input['name'] ?? '')),
    trim((string) ($input['email'] ?? '')),
    (string) ($input['password'] ?? ''),
    isset($input['username']) ? trim((string) $input['username']) : null
);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess($result['data'], $result['message']);
