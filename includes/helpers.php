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
 * انتخاب یک مقدار از فهرست مجاز
 *
 * اگر مقدار ورودی در فهرست مجاز نباشد (یا وجود نداشته باشد)، مقدار
 * پیش‌فرض بازگردانده می‌شود. برای پاک‌سازی ورودی‌هایی مانند نوع محتوا،
 * وضعیت و ترتیب که مستقیماً در کوئری استفاده می‌شوند.
 *
 * @param string[] $allowed
 */
function pickAllowed(mixed $value, array $allowed, string $default): string
{
    return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
}

/**
 * ساخت شرط جستجوی LIKE روی چند ستون
 *
 * هر ستون یک placeholder یکتا می‌گیرد. این نکته حیاتی است: چون اتصال با
 * PDO::ATTR_EMULATE_PREPARES = false کار می‌کند، یک placeholder نام‌دار را
 * نمی‌توان چند بار در یک کوئری تکرار کرد و در غیر این صورت خطای
 * «Invalid parameter number» رخ می‌دهد.
 *
 * @param string[]             $columns نام ستون‌ها (مقدار ثابت کد، نه ورودی کاربر)
 * @param string               $term    عبارت جستجو
 * @param array<string, mixed> $params  آرایه پارامترها که پر می‌شود
 */
function likeCondition(array $columns, string $term, array &$params, string $prefix = 'search'): string
{
    $parts = [];

    foreach (array_values($columns) as $i => $column) {
        $key = $prefix . '_' . $i;
        $parts[] = "$column LIKE :$key";
        $params[$key] = '%' . $term . '%';
    }

    return '(' . implode(' OR ', $parts) . ')';
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

/*
 * تابع sanitizeHtml() در includes/sanitizer.php تعریف شده است.
 * پیاده‌سازی قبلی بر پایه عبارت باقاعده بود و با نمونه‌هایی مانند
 * <svg/onload=…> یا href="java&#115;cript:…" دور زده می‌شد؛ نسخه فعلی
 * سند را با DOM تجزیه می‌کند و فهرست سفید اعمال می‌کند.
 */

/**
 * تبدیل تاریخ میلادی به شمسی
 *
 * ارقام خروجی فارسی است تا با بقیه اعداد نمایشی سایت (بازدید، تعداد
 * دیدگاه و ...) هم‌خوانی داشته باشد. برای تاریخ ماشین‌خوان در ویژگی
 * datetime باید از date('c', ...) استفاده شود، نه این تابع.
 *
 * @param string $format الگوی خروجی: Y سال، m ماه، d روز، H ساعت، i دقیقه، F نام ماه
 * @param bool   $persianDigits اگر false باشد، ارقام لاتین برگردانده می‌شود
 */
function jalaliDate(string $format, ?string $datetime = null, bool $persianDigits = true): string
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

    $result = strtr($format, array_map('strval', $replacements));

    return $persianDigits ? toPersianDigits($result) : $result;
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
 * ساخت آدرس مطلق برای یک مسیر نسبی
 *
 * از ثابت SITE_URL استفاده می‌کند، نه از هدر Host درخواست. این عمدی است:
 * نشانی مطلق در ایمیل بازیابی رمز، canonical و خوراک RSS به کار می‌رود و
 * اگر از Host خوانده شود، مهاجم می‌تواند با جعل آن لینک بازیابی را به
 * دامنه خودش هدایت کند (Host header injection).
 */
function siteUrl(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * مسیر ریشه‌نسبی برای فایل‌های ثابت و نقاط پایانی API
 *
 * بر خلاف siteUrl()، این تابع نام میزبان را وارد آدرس نمی‌کند. برای
 * CSS، جاوااسکریپت، قلم و درخواست‌های fetch باید همین استفاده شود: اگر
 * سایت با نام میزبان دیگری باز شود (IP، دامنه دوم، پشت پروکسی یا در
 * محیط توسعه)، نشانی مطلق آن فایل‌ها را cross-origin می‌کند و مرورگر
 * بارگذاری قلم و درخواست‌های same-origin را مسدود می‌کند.
 */
function assetUrl(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        // ریشه سایت از مسیر اسکریپت اجراشده استخراج می‌شود تا نصب در
        // زیرشاخه (مثلاً /blog) هم درست کار کند
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    }

    $url = $base . '/' . ltrim($path, '/');

    // مهر نسخه روی فایل‌های ظاهری
    //
    // این فایل‌ها برای سرعت مدت طولانی کش می‌شوند؛ بدون این مهر، هر
    // به‌روزرسانی ظاهری تا پایان مدت کش به کاربر نمی‌رسد و او صفحه‌ای
    // نیمه‌خراب می‌بیند بدون اینکه بداند باید کش را پاک کند.
    if (preg_match('/\.(css|js)$/i', $path)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . FARDCMS_VERSION;
    }

    return $url;
}

/**
 * آیا نشانی‌های تمیز فعال است؟
 *
 * نشانی تمیز به mod_rewrite (یا معادل آن در nginx) نیاز دارد. روی
 * بخشی از هاست‌های اشتراکی .htaccess نادیده گرفته می‌شود؛ در آن حالت
 * این گزینه خاموش می‌شود و سایت با نشانی پرسمانی کار می‌کند.
 */
function prettyUrls(): bool
{
    static $enabled = null;

    if ($enabled === null) {
        $enabled = (bool) getOption('pretty_urls', true);
    }

    return $enabled;
}

/**
 * ساخت آدرس یک مسیر داخلی سایت
 *
 * تمام پیوندهای داخلی باید از این تابع بسازند، نه siteUrl()؛ در غیر این
 * صورت با خاموش بودن نشانی تمیز، پیوندها به مسیرهایی اشاره می‌کنند که
 * سرور نمی‌شناسد.
 *
 * @param string               $path  مسیر داخلی، مثلاً 'blog/سلام'
 * @param array<string, mixed> $query پارامترهای اضافه
 */
function routeUrl(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');

    if (prettyUrls()) {
        $url = siteUrl($path);
    } else {
        $url = siteUrl('index.php');

        if ($path !== '') {
            // اسلش‌ها عمداً رمزگذاری نمی‌شوند تا آدرس خوانا بماند؛
            // http_build_query آن‌ها را به %2F تبدیل می‌کند
            $segments = array_map('rawurlencode', explode('/', $path));
            $url .= '?route=' . implode('/', $segments);
        }
    }

    if (!empty($query)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    return $url;
}

/**
 * آدرس عمومی یک نوشته یا برگه
 */
function postUrl(array $post): string
{
    $prefix = ($post['type'] ?? 'post') === 'page' ? '' : 'blog/';

    return routeUrl($prefix . ($post['slug'] ?? ''));
}
