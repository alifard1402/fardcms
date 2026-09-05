<?php
/**
 * POST /api/auth/reset-password.php
 * تعیین رمز عبور جدید با توکن بازیابی
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
apiRateLimit(10, 900, 'password_reset');

$input = getJsonInput();
$token = trim((string) ($input['token'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($token === '') {
    jsonError('توکن بازیابی ارسال نشده است');
}

$errors = validatePasswordStrength($password);
if (!empty($errors)) {
    jsonError(implode(' | ', $errors));
}

$userId = validateResetToken($token);

if ($userId === null) {
    jsonError('لینک بازیابی نامعتبر یا منقضی شده است', 410);
}

setUserPassword($userId, $password, false);
markResetTokenUsed($token);

logActivity('password_reset', 'user', $userId, 'تغییر رمز عبور با لینک بازیابی');

jsonSuccess(['redirect' => 'login.php?reset=1'], 'رمز عبور شما با موفقیت تغییر کرد');
