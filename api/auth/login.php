<?php
/**
 * POST /api/auth/login.php
 * ورود کاربر با ایمیل یا نام کاربری
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);

$input = getJsonInput();
$login = trim((string) ($input['login'] ?? $input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');
$remember = !empty($input['remember']);

if ($login === '' || $password === '') {
    jsonError('لطفاً نام کاربری و رمز عبور را وارد کنید');
}

$result = authLogin($login, $password, $remember);

if (!$result['success']) {
    jsonError($result['message'], 401);
}

// توکن CSRF تازه برای درخواست‌های بعدی همین نشست
$result['data']['csrf_token'] = generateCsrfToken();

// مسیر بازگشت نسبی است، نه بر پایه SITE_URL؛ در غیر این صورت اگر سایت
// با نام میزبان دیگری باز شود، کوکی نشست به دامنه جدید فرستاده نمی‌شود
$result['data']['redirect'] = canAccessAdmin() ? 'admin/' : './';

jsonSuccess($result['data'], $result['message']);
