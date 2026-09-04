<?php
/**
 * GET|POST /api/settings/index.php
 * خواندن و ذخیره تنظیمات سایت
 */

require_once __DIR__ . '/../bootstrap.php';

$method = apiBootstrap(['GET', 'POST']);
requireCap('manage_settings');

if ($method === 'GET') {
    jsonSuccess([
        'settings'  => getSiteSettings(),
        'roles'     => array_map(
            fn($key, $role) => ['value' => $key, 'label' => $role['label']],
            array_keys(allRoles()),
            allRoles()
        ),
        'pages'     => getPosts(['type' => 'page', 'status' => 'publish', 'per_page' => 100])['items'],
        'locations' => menuLocations(),
    ]);
}

$input = getJsonInput();
$defaults = defaultOptions();

// فقط تنظیمات شناخته‌شده ذخیره می‌شوند
$saved = [];

foreach ($input as $name => $value) {
    if (!array_key_exists($name, $defaults)) {
        continue;
    }

    $saved[$name] = sanitizeSettingValue($name, $value, $defaults[$name]);
    setOption($name, $saved[$name]);
}

if (empty($saved)) {
    jsonError('هیچ تنظیم معتبری برای ذخیره ارسال نشد');
}

logActivity('update_settings', 'settings', 0, 'به‌روزرسانی ' . count($saved) . ' تنظیم');

jsonSuccess(['settings' => getSiteSettings()], 'تنظیمات ذخیره شد');

/**
 * پاک‌سازی و تبدیل نوع مقدار یک تنظیم بر اساس نوع پیش‌فرض آن
 */
function sanitizeSettingValue(string $name, mixed $value, mixed $default): mixed
{
    // مقادیر بولی
    if (is_bool($default)) {
        return (bool) $value;
    }

    // مقادیر عددی
    if (is_int($default)) {
        $number = (int) toLatinDigits((string) $value);

        return $name === 'posts_per_page' ? max(1, min(100, $number)) : $number;
    }

    // آرایه‌ها (مثل لینک‌های شبکه‌های اجتماعی)
    if (is_array($default)) {
        if (!is_array($value)) {
            return $default;
        }

        // فقط کلیدهای شناخته‌شده و آدرس‌های معتبر
        $clean = [];
        foreach ($default as $key => $fallback) {
            $url = trim((string) ($value[$key] ?? ''));
            $clean[$key] = ($url === '' || filter_var($url, FILTER_VALIDATE_URL)) ? $url : '';
        }

        return $clean;
    }

    $text = trim((string) $value);

    // نقش پیش‌فرض کاربران جدید هرگز مدیر کل نمی‌شود
    if ($name === 'default_role') {
        return (isValidRole($text) && $text !== 'administrator') ? $text : 'subscriber';
    }

    // متن پاورقی فقط HTML درون‌خطی محدود می‌پذیرد (پیوند و تأکید)
    if ($name === 'footer_text') {
        return mb_substr(sanitizeInlineHtml($text), 0, 1000, 'UTF-8');
    }

    // کد رهگیری فقط شناسه، نه اسکریپت
    if ($name === 'google_analytics') {
        return preg_match('/^[A-Za-z0-9\-]{0,30}$/', $text) ? $text : '';
    }

    return mb_substr(strip_tags($text), 0, 500, 'UTF-8');
}
