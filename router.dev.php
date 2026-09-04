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

// فایل واقعی (تصویر، CSS، اسکریپت PHP در api و ...) را خود سرور ارائه می‌دهد
if ($path !== '/' && is_file($target)) {
    return false;
}

// پوشه‌هایی که فایل index.html دارند، مانند /admin/
if (is_dir($target) && is_file(rtrim($target, '/') . '/index.html')) {
    return false;
}

// بقیه درخواست‌ها به کنترلر اصلی سایت سپرده می‌شود
require __DIR__ . '/index.php';
