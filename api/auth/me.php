<?php
/**
 * GET /api/auth/me.php
 * اطلاعات کاربر فعلی، توکن CSRF و داده‌های اولیه پنل مدیریت
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);

if (!isLoggedIn()) {
    jsonSuccess([
        'authenticated' => false,
        'settings'      => getPublicSettings(),
    ]);
}

$user = getCurrentUserRecord();

jsonSuccess([
    'authenticated' => true,
    'user'          => $user !== null ? formatUserRow($user) + ['caps' => roleCaps($user['role'])] : null,
    'csrf_token'    => generateCsrfToken(),
    'can_access_admin' => canAccessAdmin(),
    'roles'         => array_map(
        fn($key, $role) => ['value' => $key, 'label' => $role['label']],
        array_keys(allRoles()),
        allRoles()
    ),
    'settings'      => getPublicSettings(),
    // اسکریپت پنلِ افزونه‌های فعال؛ پنل آن‌ها را پیش از راه‌اندازی
    // مسیریاب بارگذاری می‌کند
    'plugin_scripts' => canAccessAdmin() ? pluginAdminScripts() : [],
]);
