<?php
/**
 * توابع کمکی عمومی
 */

/**
 * فرار دادن خروجی برای نمایش در HTML
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * ساخت نامک (slug) از یک عنوان — با پشتیبانی کامل از فارسی
 */
function slugify(string $text): string
{
    $text = trim($text);

    // یکسان‌سازی کاراکترهای عربی/فارسی
    $text = str_replace(['ي', 'ك', 'ٱ', 'أ', 'إ', 'ﻻ'], ['ی', 'ک', 'ا', 'ا', 'ا', 'لا'], $text);

    // حذف اعراب
    $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', $text);

    // تبدیل فاصله‌ها و جداکننده‌ها به خط تیره
    $text = preg_replace('/[\s\x{200C}\x{200F}_]+/u', '-', $text);

    // نگه‌داشتن حروف فارسی، لاتین، اعداد و خط تیره
    $text = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $text);

    $text = preg_replace('/-+/', '-', $text);
    $text = trim($text, '-');
    $text = mb_strtolower($text, 'UTF-8');

    return $text !== '' ? mb_substr($text, 0, 190, 'UTF-8') : 'item-' . substr(md5(uniqid('', true)), 0, 8);
}

/**
 * ساخت نامک یکتا در یک جدول مشخص
 *
 * @param string   $table     نام جدول (بدون پیشوند)
 * @param string   $slug      نامک پیشنهادی
 * @param int|null $ignoreId  شناسه‌ای که در بررسی تکراری بودن نادیده گرفته می‌شود
 * @param array    $extra     شرط‌های اضافه، مثلاً ['taxonomy' => 'category']
 */
function uniqueSlug(string $table, string $slug, ?int $ignoreId = null, array $extra = []): string
{
    $db = Database::getConnection();
    $base = $slug;
    $suffix = 1;

    $conditions = ['slug = :slug'];
    $params = [];

    if ($ignoreId !== null) {
        $conditions[] = 'id <> :ignore_id';
        $params['ignore_id'] = $ignoreId;
    }

    foreach (array_keys($extra) as $i => $column) {
        if (!preg_match('/^[a-z_]+$/', $column)) {
            throw new InvalidArgumentException('نام ستون نامعتبر است');
        }
        $conditions[] = "`$column` = :extra_$i";
        $params["extra_$i"] = array_values($extra)[$i];
    }

    $sql = 'SELECT id FROM ' . tbl($table) . ' WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1';
    $stmt = $db->prepare($sql);

    while (true) {
        $stmt->execute($params + ['slug' => $slug]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $base . '-' . (++$suffix);
    }
}

/**
 * ساخت خلاصه از محتوای HTML
 */
function makeExcerpt(string $content, int $length = 160): string
{
    $text = strip_tags($content);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length, 'UTF-8'), ' ،.') . '…';
}

/**
 * پاک‌سازی HTML ورودی کاربر — حذف تگ‌ها و ویژگی‌های خطرناک
 *
 * کاربران با دسترسی انتشار می‌توانند HTML محدود بنویسند، اما اسکریپت،
 * iframe و رویدادهای on* حذف می‌شوند تا از XSS ذخیره‌شده جلوگیری شود.
 */
function sanitizeHtml(string $html): string
{
    // حذف کامل تگ‌های خطرناک همراه با محتوایشان
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|base|link|meta)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|base|link|meta)\b[^>]*/?>#i', '', $html);

    // حذف ویژگی‌های رویداد (onclick، onerror و ...)
    $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

    // حذف پروتکل‌های اجرایی در href/src
    $html = preg_replace('/\s(href|src|xlink:href)\s*=\s*("|\')\s*(javascript|vbscript|data\s*:\s*text\/html)[^"\']*\2/i', '', $html);

    return trim($html);
}

/**
 * تبدیل تاریخ میلادی به شمسی
 *
 * @param string $format الگوی خروجی: Y سال، m ماه، d روز، H ساعت، i دقیقه، F نام ماه
 */
function jalaliDate(string $format, ?string $datetime = null): string
{
    $timestamp = $datetime !== null ? strtotime($datetime) : time();

    if ($timestamp === false) {
        return '';
    }

    [$gy, $gm, $gd] = array_map('intval', explode('-', date('Y-n-j', $timestamp)));
    [$jy, $jm, $jd] = gregorianToJalali($gy, $gm, $gd);

    $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
               'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    $replacements = [
        'Y' => $jy,
        'y' => substr((string) $jy, -2),
        'm' => str_pad((string) $jm, 2, '0', STR_PAD_LEFT),
        'n' => $jm,
        'd' => str_pad((string) $jd, 2, '0', STR_PAD_LEFT),
        'j' => $jd,
        'F' => $months[$jm - 1],
        'H' => date('H', $timestamp),
        'i' => date('i', $timestamp),
        's' => date('s', $timestamp),
    ];

    return strtr($format, array_map('strval', $replacements));
}

/**
 * تبدیل تاریخ میلادی به شمسی (الگوریتم استاندارد)
 *
 * @return array{0:int,1:int,2:int} [سال، ماه، روز]
 */
function gregorianToJalali(int $gy, int $gm, int $gd): array
{
    // روزهای تجمعی ابتدای هر ماه میلادی (غیرکبیسه)
    $gCumulative = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;

    $gy2 = ($gm > 2) ? $gy + 1 : $gy;

    $days = (365 * $gy)
          + (int) (($gy2 + 3) / 4)
          - (int) (($gy2 + 99) / 100)
          + (int) (($gy2 + 399) / 400)
          - 80 + $gd + $gCumulative[$gm - 1];

    $jy += 33 * (int) ($days / 12053);
    $days %= 12053;

    $jy += 4 * (int) ($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int) (($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + (int) ($days / 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + (int) (($days - 186) / 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

/**
 * فاصله زمانی به صورت خوانا («۳ ساعت پیش»)
 */
function timeAgo(string $datetime): string
{
    $diff = time() - (int) strtotime($datetime);

    if ($diff < 60) {
        return 'لحظه‌ای پیش';
    }

    $units = [
        31536000 => 'سال',
        2592000  => 'ماه',
        604800   => 'هفته',
        86400    => 'روز',
        3600     => 'ساعت',
        60       => 'دقیقه',
    ];

    foreach ($units as $seconds => $label) {
        if ($diff >= $seconds) {
            return toPersianDigits((string) (int) ($diff / $seconds)) . ' ' . $label . ' پیش';
        }
    }

    return 'لحظه‌ای پیش';
}

/**
 * تبدیل ارقام لاتین به فارسی
 */
function toPersianDigits(string $text): string
{
    return str_replace(
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
        $text
    );
}

/**
 * تبدیل ارقام فارسی/عربی به لاتین (برای ورودی فرم‌ها)
 */
function toLatinDigits(string $text): string
{
    return str_replace(
        ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        $text
    );
}

/**
 * تبدیل حجم بایت به رشته خوانا
 */
function formatBytes(int $bytes, int $precision = 1): string
{
    $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * ساخت آدرس کامل برای یک مسیر نسبی
 */
function siteUrl(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * آدرس عمومی یک نوشته یا برگه
 */
function postUrl(array $post): string
{
    $prefix = ($post['type'] ?? 'post') === 'page' ? '' : 'blog/';

    return siteUrl($prefix . ($post['slug'] ?? ''));
}
