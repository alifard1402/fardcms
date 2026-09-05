<?php
/**
 * POST /api/auth/logout.php
 * خروج کاربر از حساب
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);

if (isLoggedIn()) {
    logActivity('logout', 'user', currentUserId(), 'خروج از سیستم');
    authLogout();
}

// نشانی کامل و نه نسبی: این پاسخ را پنل مدیریت مصرف می‌کند که خودش
// در /admin/ است، پس مقدار نسبی «login.php» به /admin/login.php تفسیر
// می‌شد و کاربر پس از خروج به صفحه‌ای می‌رسید که وجود ندارد.
jsonSuccess(['redirect' => siteUrl('login.php')], 'با موفقیت خارج شدید');
