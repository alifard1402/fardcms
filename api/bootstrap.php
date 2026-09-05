<?php
/**
 * راه‌انداز مشترک تمام نقاط پایانی API
 *
 * هر فایل API تنها این فایل را require می‌کند و سپس apiBootstrap() را
 * با روش‌های مجاز خود صدا می‌زند.
 */

require_once dirname(__DIR__) . '/config.php';

// افزونه‌های فعال باید پیش از پاسخ‌دهی نقاط پایانی روی قلاب‌ها بنشینند
loadPlugins();

// خطاهای مدیریت‌نشده باید پاسخ JSON بدهند، نه صفحه خطای HTML
set_exception_handler(function (Throwable $e): void {
    error_log('Unhandled API exception: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => DEBUG_MODE ? $e->getMessage() : 'خطای داخلی سرور',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});
