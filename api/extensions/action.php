<?php
/**
 * POST /api/extensions/action.php
 * فعال، غیرفعال یا حذف کردن یک قالب یا افزونه
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('manage_settings');

$input = getJsonInput();
$kind = (string) ($input['kind'] ?? '');
$slug = (string) ($input['slug'] ?? '');
$action = (string) ($input['action'] ?? '');

if (!in_array($kind, ['theme', 'plugin'], true)) {
    jsonError('نوع نامعتبر است');
}

// حذف، فایل از سرور پاک می‌کند؛ مثل نصب فقط دست مدیر کل است
if ($action === 'delete' && !isAdmin()) {
    jsonError('فقط مدیر کل می‌تواند قالب یا افزونه حذف کند', 403);
}

$result = match (true) {
    $kind === 'theme' && $action === 'activate' => activateThemeBySlug($slug),
    $kind === 'plugin' && $action === 'activate' => activatePlugin($slug),
    $kind === 'plugin' && $action === 'deactivate' => deactivatePlugin($slug),
    $action === 'delete' => removePackage($slug, $kind),
    default => ['success' => false, 'message' => 'عملیات نامعتبر است'],
};

if (!$result['success']) {
    jsonError($result['message']);
}

logActivity("{$action}_{$kind}", $kind, 0, "$action: $slug");

jsonSuccess([
    'themes'       => installedThemes(),
    'plugins'      => installedPlugins(),
    'active_theme' => (string) getOption('active_theme', 'default'),
], $result['message']);
