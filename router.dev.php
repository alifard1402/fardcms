<?php
/**
 * مسیریاب سرور توسعه PHP
 *
 * سرور داخلی PHP از .htaccess پشتیبانی نمی‌کند، بنابراین برای داشتن
 * نشانی‌های تمیز در محیط توسعه این فایل استفاده می‌شود:
 *
 *   php -S localhost:8765 router.dev.php
 *
 * در محیط واقعی به‌جای این فایل، .htaccess یا nginx.conf.example
 * وظیفه مسیریابی را انجام می‌دهد.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$target = __DIR__ . urldecode($path);

// معادل themes/.htaccess و plugins/.htaccess: کد قالب و افزونه فقط از
// داخل سیستم اجرا می‌شود، نه با درخواست مستقیم
if (preg_match('#^/(themes|plugins)/.+\.(php|phtml|inc)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

// نشانی‌های قدیمی صفحه‌های ورودی، معادل قاعده‌های .htaccess
if (preg_match('#^/(login|register|forgot-password|reset-password)\.html$#', $path, $m)) {
    require __DIR__ . '/' . $m[1] . '.php';
    return true;
}

if ($path === '/admin/index.html') {
    require __DIR__ . '/admin/index.php';
    return true;
}

// فایل واقعی (تصویر، CSS، اسکریپت PHP در api و ...) را خود سرور ارائه می‌دهد
if ($path !== '/' && is_file($target)) {
    return false;
}

// پوشه‌هایی که صفحه پیش‌فرض دارند، مانند /admin/
foreach (['index.php', 'index.html'] as $indexFile) {
    $index = rtrim($target, '/') . '/' . $indexFile;

    if (is_dir($target) && is_file($index)) {
        if ($indexFile === 'index.php') {
            require $index;
            return true;
        }

        return false;
    }
}

// بقیه درخواست‌ها به کنترلر اصلی سایت سپرده می‌شود
require __DIR__ . '/index.php';
