<?php
/**
 * POST /api/extensions/install.php
 * نصب قالب یا افزونه از فایل زیپ
 *
 * این نقطه پایانی کدِ اجراشدنی روی سرور می‌نشاند، پس فقط برای مدیر کل
 * باز است و بسته پیش از استخراج کامل بازرسی می‌شود (includes/package.php).
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('manage_settings');

if (!isAdmin()) {
    jsonError('فقط مدیر کل می‌تواند قالب یا افزونه نصب کند', 403);
}

$kind = (string) ($_POST['kind'] ?? '');
$overwrite = !empty($_POST['overwrite']);

if (empty($_FILES['package'])) {
    jsonError('فایلی ارسال نشده است');
}

$result = installPackage($_FILES['package'], $kind, $overwrite);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess([
    'slug'    => $result['slug'] ?? '',
    'themes'  => installedThemes(),
    'plugins' => installedPlugins(),
], $result['message']);
