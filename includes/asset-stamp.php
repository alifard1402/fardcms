<?php
/**
 * مهر یکتای فایل‌های ظاهری برای شکستن کش
 *
 * ایده ساده است: به‌جای شماره نسخه دستی، عددی به آدرس فایل می‌چسبانیم
 * که خودش با تغییر فایل عوض می‌شود. مرورگر (و CDN) آدرس تازه را فایل
 * تازه می‌بیند و اصلاً سراغ کش نمی‌رود:
 *
 *     admin.css?v=1757068142   ← پیش از به‌روزرسانی
 *     admin.css?v=1757331905   ← پس از آپلود فایل جدید
 *
 * چرا زمانِ تغییر فایل و نه time()؟
 * time() هم کش را می‌شکند، اما چون در هر بار باز شدن صفحه عوض می‌شود،
 * مرورگر همه‌چیز را در هر کلیک دوباره دانلود می‌کند و پنل کند می‌شود.
 * filemtime دقیقاً همان اثر را دارد — با آپلود فایل جدید، عدد جدید —
 * ولی تا وقتی فایل دست‌نخورده است ثابت می‌ماند و کش کار خودش را می‌کند.
 *
 * این فایل عمداً به هیچ چیز وابسته نیست (نه دیتابیس، نه config.php) تا
 * بتوان آن را از صفحه‌های ورودی هم صدا زد.
 */

if (!function_exists('assetStamp')) {
    /**
     * مهر «?v=…» برای یک فایل، نسبت به ریشه پروژه
     *
     * @param string $relativePath مثلاً 'admin/assets/css/admin.css'
     * @param bool   $asQuery      با ? شروع شود یا فقط مقدار برگردد
     */
    function assetStamp(string $relativePath, bool $asQuery = true): string
    {
        static $cache = [];

        $key = ltrim($relativePath, '/');

        if (!isset($cache[$key])) {
            $file = dirname(__DIR__) . '/' . $key;
            $mtime = is_file($file) ? filemtime($file) : false;

            // اگر فایل خوانده نشد، به نسخه سیستم برمی‌گردیم؛ بدون مهر
            // رها نمی‌کنیم چون همان حالت است که کش کهنه را نگه می‌دارد.
            $cache[$key] = $mtime !== false
                ? (string) $mtime
                : (defined('FARDCMS_VERSION') ? FARDCMS_VERSION : (string) time());
        }

        return ($asQuery ? '?v=' : '') . $cache[$key];
    }
}
