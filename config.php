<?php
/**
 * تنظیمات اصلی FardCMS
 * این فایل را مطابق محیط خود ویرایش کنید
 */

// ─── تنظیمات دیتابیس ───────────────────────────────────────
define('DB_HOST', getenv('FARDCMS_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('FARDCMS_DB_NAME') ?: 'fardcms');
define('DB_USER', getenv('FARDCMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('FARDCMS_DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_PREFIX', 'fc_');

// ─── تنظیمات سایت ──────────────────────────────────────────
define('SITE_NAME', 'فرد سی‌ام‌اس');
define('SITE_URL', getenv('FARDCMS_URL') ?: 'http://localhost:8765');
define('SITE_LANG', 'fa');

// ─── کلید امنیتی ───────────────────────────────────────────
// یک رشته تصادفی قوی برای رمزنگاری توکن‌ها
define('SECRET_KEY', getenv('FARDCMS_SECRET') ?: 'change-this-to-a-random-secure-string-in-production');

// ─── تنظیمات Session ───────────────────────────────────────
define('SESSION_LIFETIME', 7200);       // ۲ ساعت (ثانیه)
define('REMEMBER_LIFETIME', 2592000);   // ۳۰ روز (ثانیه)
define('SESSION_NAME', 'FARDCMS_SESSION');

// ─── تنظیمات رمز عبور ──────────────────────────────────────
define('BCRYPT_COST', 12);
define('MIN_PASSWORD_LENGTH', 8);

// ─── تنظیمات Rate Limiting ─────────────────────────────────
define('RATE_LIMIT_MAX', 10);          // حداکثر تلاش ورود
define('RATE_LIMIT_WINDOW', 900);      // پنجره زمانی (ثانیه) - ۱۵ دقیقه

// ─── تنظیمات آپلود ─────────────────────────────────────────
define('UPLOAD_MAX_SIZE', 8 * 1024 * 1024);   // ۸ مگابایت
define('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,gif,webp,svg,pdf,zip,mp4,mp3,docx,xlsx');
define('THUMBNAIL_WIDTH', 400);
define('THUMBNAIL_HEIGHT', 300);

// ─── مسیرها ────────────────────────────────────────────────
define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('THEMES_PATH', BASE_PATH . '/themes');
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_URL', SITE_URL . '/uploads');

// ─── حالت توسعه ────────────────────────────────────────────
define('DEBUG_MODE', (getenv('FARDCMS_DEBUG') === '1'));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

// ─── بارگذاری خودکار includes ──────────────────────────────
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/response.php';
require_once INCLUDES_PATH . '/roles.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/middleware.php';
require_once INCLUDES_PATH . '/settings.php';
require_once INCLUDES_PATH . '/users.php';
require_once INCLUDES_PATH . '/activity.php';
require_once INCLUDES_PATH . '/posts.php';
require_once INCLUDES_PATH . '/taxonomy.php';
require_once INCLUDES_PATH . '/media.php';
require_once INCLUDES_PATH . '/comments.php';
require_once INCLUDES_PATH . '/menus.php';
require_once INCLUDES_PATH . '/theme.php';
