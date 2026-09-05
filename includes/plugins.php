<?php
/**
 * مدیریت افزونه‌ها
 *
 * هر افزونه یک پوشه در plugins/ است با این ساختار کمینه:
 *
 *   plugins/gallery/
 *     plugin.json     شناسنامه: نام، نسخه، توضیح، فایل اصلی
 *     gallery.php     فایل اصلی که قلاب‌هایش را ثبت می‌کند
 *     assets/admin.js (اختیاری) کد پنل مدیریت
 *
 * افزونه‌های فعال در تنظیمات ذخیره می‌شوند و در هر درخواست بارگذاری
 * می‌شوند. فایل اصلی نباید در زمان بارگذاری کاری جز تعریف تابع و ثبت
 * قلاب انجام دهد؛ در آن لحظه هنوز نشست و کاربر جاری آماده نیستند.
 */

/** ریشه افزونه‌ها؛ در نصب‌های قدیمی که ثابت را ندارند هم کار می‌کند */
function pluginsPath(): string
{
    return defined('PLUGINS_PATH') ? PLUGINS_PATH : BASE_PATH . '/plugins';
}

/**
 * خواندن شناسنامه یک افزونه از روی دیسک
 *
 * @return array{slug:string, name:string, description:string, version:string,
 *               author:string, main:string, requires:string}|null
 */
function readPluginManifest(string $slug): ?array
{
    // نام پوشه مستقیم در ساخت مسیر به کار می‌رود، پس محدود می‌شود
    if (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
        return null;
    }

    $dir = pluginsPath() . '/' . $slug;

    if (!is_dir($dir)) {
        return null;
    }

    $meta = [];

    if (is_readable("$dir/plugin.json")) {
        $decoded = json_decode((string) file_get_contents("$dir/plugin.json"), true);
        $meta = is_array($decoded) ? $decoded : [];
    }

    // فایل اصلی: یا در شناسنامه آمده یا هم‌نام پوشه است
    $main = basename((string) ($meta['main'] ?? "$slug.php"));

    if (!is_file("$dir/$main")) {
        return null;
    }

    return [
        'slug'        => $slug,
        'name'        => (string) ($meta['name'] ?? $slug),
        'description' => (string) ($meta['description'] ?? ''),
        'version'     => (string) ($meta['version'] ?? ''),
        'author'      => (string) ($meta['author'] ?? ''),
        'main'        => $main,
        'requires'    => (string) ($meta['requires'] ?? ''),
    ];
}

/**
 * تمام افزونه‌های نصب‌شده
 *
 * @return array<int, array<string, mixed>>
 */
function installedPlugins(): array
{
    $active = activePlugins();
    $list = [];

    foreach ((array) glob(pluginsPath() . '/*', GLOB_ONLYDIR) as $dir) {
        $manifest = readPluginManifest(basename($dir));

        if ($manifest === null) {
            continue;
        }

        $manifest['active'] = in_array($manifest['slug'], $active, true);
        $manifest['admin_script'] = is_file("$dir/assets/admin.js");
        $list[] = $manifest;
    }

    usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));

    return $list;
}

/**
 * نامک افزونه‌های فعال
 *
 * @return string[]
 */
function activePlugins(): array
{
    $stored = getOption('active_plugins', []);

    return is_array($stored) ? array_values(array_filter($stored, 'is_string')) : [];
}

/**
 * بارگذاری افزونه‌های فعال
 *
 * از هر نقطه ورود (سایت، API، پنل) یک بار صدا زده می‌شود. عمداً در
 * config.php صدا زده نمی‌شود، چون آن فایل متعلق به کاربر است و در
 * به‌روزرسانی جایگزین نمی‌شود؛ نصب‌های موجود هرگز آن را نمی‌دیدند.
 */
function loadPlugins(): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    foreach (activePlugins() as $slug) {
        $manifest = readPluginManifest($slug);

        if ($manifest === null) {
            // افزونه پاک شده ولی هنوز در فهرست فعال‌هاست
            error_log("Active plugin not found: $slug");
            continue;
        }

        try {
            require_once pluginsPath() . '/' . $slug . '/' . $manifest['main'];
        } catch (Throwable $e) {
            // یک افزونه معیوب نباید کل سایت را از کار بیندازد
            error_log("Plugin '$slug' failed to load: " . $e->getMessage());
        }
    }

    doAction('plugins_loaded');
}

/**
 * فعال کردن یک افزونه
 *
 * @return array{success:bool, message:string}
 */
function activatePlugin(string $slug): array
{
    $manifest = readPluginManifest($slug);

    if ($manifest === null) {
        return ['success' => false, 'message' => 'افزونه یافت نشد یا فایل اصلی‌اش وجود ندارد'];
    }

    if ($manifest['requires'] !== '' && defined('FARDCMS_VERSION')
        && version_compare(FARDCMS_VERSION, $manifest['requires'], '<')) {
        return ['success' => false, 'message' =>
            "این افزونه به فرد سی‌ام‌اس {$manifest['requires']} یا بالاتر نیاز دارد"];
    }

    $active = activePlugins();

    if (!in_array($slug, $active, true)) {
        $active[] = $slug;
        setOption('active_plugins', array_values($active));
    }

    // قلاب فعال‌سازی برای کارهایی مثل ساخت جدول یا مقدار پیش‌فرض
    require_once pluginsPath() . '/' . $slug . '/' . $manifest['main'];
    doAction("activate_plugin_$slug");

    return ['success' => true, 'message' => "افزونه «{$manifest['name']}» فعال شد"];
}

/**
 * غیرفعال کردن یک افزونه
 *
 * @return array{success:bool, message:string}
 */
function deactivatePlugin(string $slug): array
{
    $active = activePlugins();
    $filtered = array_values(array_filter($active, fn($item) => $item !== $slug));

    if (count($filtered) === count($active)) {
        return ['success' => false, 'message' => 'این افزونه فعال نیست'];
    }

    setOption('active_plugins', $filtered);
    doAction("deactivate_plugin_$slug");

    return ['success' => true, 'message' => 'افزونه غیرفعال شد'];
}

/**
 * نشانی فایل جاوااسکریپت پنل یک افزونه
 *
 * نشانی کامل ساخته می‌شود، چون این فهرست از داخل api/ صدا زده می‌شود و
 * نشانی نسبی آنجا به مسیر اشتباه حل می‌شود.
 */
function pluginAdminScripts(): array
{
    $urls = [];

    foreach (activePlugins() as $slug) {
        $manifest = readPluginManifest($slug);

        if ($manifest !== null && is_file(pluginsPath() . "/$slug/assets/admin.js")) {
            $file = pluginsPath() . "/$slug/assets/admin.js";
            $urls[] = siteUrl("plugins/$slug/assets/admin.js") . '?v=' . filemtime($file);
        }
    }

    return $urls;
}
