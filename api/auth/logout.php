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

jsonSuccess(['redirect' => 'login.html'], 'با موفقیت خارج شدید');
