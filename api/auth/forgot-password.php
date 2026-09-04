<?php
/**
 * POST /api/auth/forgot-password.php
 * درخواست بازیابی رمز عبور
 */

require_once __DIR__ . '/../bootstrap.php';
require_once TEMPLATES_PATH . '/email/mailer.php';

apiBootstrap(['POST']);

// محدودیت سخت‌گیرانه‌تر برای جلوگیری از سوءاستفاده
apiRateLimit(5, 900, 'password_reset');

$input = getJsonInput();
$email = trim((string) ($input['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('فرمت ایمیل نامعتبر است');
}

$stmt = Database::getConnection()->prepare(
    'SELECT id, name, email FROM ' . tbl('users') . ' WHERE email = ? AND is_active = 1 LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// پاسخ همیشه یکسان است تا وجود یا نبود یک ایمیل افشا نشود
if ($user) {
    $token = createPasswordResetToken((int) $user['id']);
    sendPasswordResetEmail($user['email'], $user['name'], $token);

    logActivity('password_reset_request', 'user', (int) $user['id'], 'درخواست بازیابی رمز عبور');
}

jsonSuccess([], 'اگر این ایمیل در سیستم ثبت شده باشد، لینک بازیابی برای شما ارسال می‌شود');
