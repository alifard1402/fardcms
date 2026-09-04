<?php
/**
 * تنظیمات سایت — ذخیره در جدول options (مشابه wp_options)
 */

/** @var array<string, mixed>|null کش تنظیمات در هر درخواست */
$GLOBALS['fardcms_options_cache'] = null;

/**
 * بارگذاری تمام تنظیمات (یک کوئری در هر درخواست)
 *
 * @return array<string, mixed>
 */
function loadOptions(): array
{
    if ($GLOBALS['fardcms_options_cache'] !== null) {
        return $GLOBALS['fardcms_options_cache'];
    }

    $options = [];

    try {
        $rows = Database::getConnection()
            ->query('SELECT option_name, option_value FROM ' . tbl('options'))
            ->fetchAll();

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['option_value'], true);
            $options[$row['option_name']] = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : $row['option_value'];
        }
    } catch (Throwable $e) {
        error_log('Failed to load options: ' . $e->getMessage());
    }

    return $GLOBALS['fardcms_options_cache'] = $options;
}

/**
 * خواندن یک تنظیم
 */
function getOption(string $name, mixed $default = null): mixed
{
    $options = loadOptions();

    return $options[$name] ?? $default;
}

/**
 * ذخیره یک تنظیم
 */
function setOption(string $name, mixed $value): void
{
    $db = Database::getConnection();

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('options') . ' (option_name, option_value)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)'
    );
    $stmt->execute([$name, json_encode($value, JSON_UNESCAPED_UNICODE)]);

    // به‌روزرسانی کش
    if ($GLOBALS['fardcms_options_cache'] !== null) {
        $GLOBALS['fardcms_options_cache'][$name] = $value;
    }
}

/**
 * حذف یک تنظیم
 */
function deleteOption(string $name): void
{
    Database::getConnection()
        ->prepare('DELETE FROM ' . tbl('options') . ' WHERE option_name = ?')
        ->execute([$name]);

    if ($GLOBALS['fardcms_options_cache'] !== null) {
        unset($GLOBALS['fardcms_options_cache'][$name]);
    }
}

/**
 * تنظیمات پیش‌فرض سایت
 *
 * @return array<string, mixed>
 */
function defaultOptions(): array
{
    return [
        'site_title'         => SITE_NAME,
        'site_tagline'       => 'یک سیستم مدیریت محتوای مدرن',
        'site_description'   => 'وب‌سایتی ساخته‌شده با FardCMS',
        'site_logo'          => '',
        'site_favicon'       => '',
        'posts_per_page'     => 10,
        'default_role'       => 'subscriber',
        'allow_registration' => true,
        'allow_comments'     => true,
        'moderate_comments'  => true,
        'active_theme'       => 'default',
        'pretty_urls'        => true,
        'date_format'        => 'j F Y',
        'front_page'         => 'blog',   // blog یا شناسه یک برگه
        'social_links'       => ['telegram' => '', 'instagram' => '', 'x' => '', 'github' => ''],
        'footer_text'        => '',
        'maintenance_mode'   => false,
        'seo_meta_image'     => '',
        'google_analytics'   => '',
    ];
}

/**
 * تنظیمات سایت همراه با مقادیر پیش‌فرض
 *
 * @return array<string, mixed>
 */
function getSiteSettings(): array
{
    $defaults = defaultOptions();
    $stored = loadOptions();

    return array_merge($defaults, array_intersect_key($stored, $defaults));
}

/**
 * تنظیماتی که برای بازدیدکننده عمومی قابل نمایش است
 *
 * @return array<string, mixed>
 */
function getPublicSettings(): array
{
    $settings = getSiteSettings();

    return [
        'site_title'         => $settings['site_title'],
        'site_tagline'       => $settings['site_tagline'],
        'site_description'   => $settings['site_description'],
        'site_logo'          => $settings['site_logo'],
        'site_favicon'       => $settings['site_favicon'],
        'allow_registration' => (bool) $settings['allow_registration'],
        'allow_comments'     => (bool) $settings['allow_comments'],
        'social_links'       => $settings['social_links'],
        'footer_text'        => $settings['footer_text'],
    ];
}
