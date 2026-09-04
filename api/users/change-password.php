<?php
/**
 * POST /api/users/change-password.php
 * تغییر رمز عبور کاربر فعلی
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireAuth();
apiRateLimit(10, 600, 'password_change');

$input = getJsonInput();
$current = (string) ($input['current_password'] ?? '');
$new = (string) ($input['new_password'] ?? '');

if ($current === '' || $new === '') {
    jsonError('رمز عبور فعلی و جدید را وارد کنید');
}

// رمز فعلی باید تأیید شود
$stmt = Database::getConnection()->prepare(
    'SELECT password FROM ' . tbl('users') . ' WHERE id = ? LIMIT 1'
);
$stmt->execute([currentUserId()]);
$row = $stmt->fetch();

if (!$row || !password_verify($current, $row['password'])) {
    jsonError('رمز عبور فعلی اشتباه است', 401);
}

if ($current === $new) {
    jsonError('رمز عبور جدید باید با رمز فعلی متفاوت باشد');
}

$errors = validatePasswordStrength($new);
if (!empty($errors)) {
    jsonError(implode(' | ', $errors));
}

// سایر نشست‌ها خارج می‌شوند، نشست فعلی باقی می‌ماند
setUserPassword(currentUserId(), $new, true);

logActivity('change_password', 'user', currentUserId(), 'تغییر رمز عبور');

jsonSuccess([], 'رمز عبور با موفقیت تغییر کرد. سایر دستگاه‌ها از حساب خارج شدند.');
