<?php
/**
 * گالری تصویر و ویدیوی نوشته‌ها
 *
 * این‌ها روی فیلدهای سفارشی (postmeta) سوار شده‌اند تا نیازی به جدول
 * تازه نباشد. گالری فقط «شناسه» رسانه‌ها را نگه می‌دارد و نه نشانی آن‌ها،
 * چون نشانی با تغییر دامنه یا انتقال هاست عوض می‌شود ولی شناسه نه.
 *
 * ویدیو دو حالت دارد:
 *   aparat — روی سرور آپارات است و پهنای باند هاست را مصرف نمی‌کند
 *   file   — فایلی که خود کاربر آپلود کرده و با تگ <video> پخش می‌شود
 *
 * چرا کد امبد آپارات را مستقیم از کاربر نمی‌گیریم: پاک‌سازی‌گر HTML
 * عمداً iframe را حذف می‌کند. اینجا فقط «شناسه» ذخیره می‌شود و خودِ قالب
 * iframe را با نشانی ثابت آپارات می‌سازد؛ پس ورودی کاربر هرگز به HTML
 * تبدیل نمی‌شود و آن لایه امنیتی دست‌نخورده می‌ماند.
 */

/**
 * فیلدهای سفارشی‌ای که از بیرون قابل ذخیره‌اند
 *
 * بدون این فهرست، هر کلیدی که در درخواست بیاید در دیتابیس می‌نشیند.
 *
 * @return array<string, string> کلید => نوع
 */
function allowedPostMetaKeys(): array
{
    return [
        'gallery'        => 'ids',     // شناسه تصویرهای گالری
        'video_provider' => 'provider',
        'video_aparat'   => 'aparat',  // شناسه ویدیوی آپارات
        'video_file'     => 'url',     // نشانی فایل ویدیوی آپلودشده
        'video_poster'   => 'url',     // تصویر پیش‌نمایش ویدیو
        'subtitle'       => 'text',    // زیرعنوان نمونه‌کار
        'shoot_location' => 'text',    // محل عکاسی
    ];
}

/**
 * پاک‌سازی فیلدهای سفارشی ورودی
 *
 * @param  array<string, mixed> $meta
 * @return array<string, mixed>
 */
function sanitizePostMeta(array $meta): array
{
    $clean = [];

    foreach (allowedPostMetaKeys() as $key => $type) {
        if (!array_key_exists($key, $meta)) {
            continue;
        }

        $value = $meta[$key];

        $clean[$key] = match ($type) {
            // حداکثر ۱۰۰ تصویر: جلوی پر شدن دیتابیس با یک درخواست را می‌گیرد
            'ids' => array_values(array_slice(
                array_unique(array_filter(array_map('intval', (array) $value), fn($id) => $id > 0)),
                0,
                100
            )),
            'provider' => in_array($value, ['aparat', 'file'], true) ? $value : '',
            'aparat'   => aparatVideoId((string) $value),
            'url'      => filter_var(trim((string) $value), FILTER_VALIDATE_URL)
                          || str_starts_with(trim((string) $value), '/')
                              ? trim((string) $value) : '',
            default    => mb_substr(trim(strip_tags((string) $value)), 0, 200, 'UTF-8'),
        };
    }

    return $clean;
}

/**
 * استخراج شناسه ویدیوی آپارات
 *
 * هم شناسه خام پذیرفته می‌شود و هم نشانی کامل صفحه یا کد امبد، چون کاربر
 * معمولاً همان چیزی را می‌چسباند که از آپارات کپی کرده است.
 */
function aparatVideoId(string $input): string
{
    $input = trim($input);

    if ($input === '') {
        return '';
    }

    // نشانی صفحه ویدیو یا نشانی امبد
    if (preg_match('#aparat\.com/(?:v/|video/video/embed/videohash/)([A-Za-z0-9]+)#i', $input, $m)) {
        return $m[1];
    }

    return preg_match('/^[A-Za-z0-9]{4,32}$/', $input) ? $input : '';
}

/**
 * نشانی پخش‌کننده آپارات
 */
function aparatEmbedUrl(string $videoId): string
{
    return 'https://www.aparat.com/video/video/embed/videohash/'
         . rawurlencode($videoId) . '/vt/frame';
}

/**
 * تصویرهای گالری یک نوشته
 *
 * رسانه‌های حذف‌شده کنار گذاشته می‌شوند تا قالب با آدرس شکسته روبه‌رو نشود.
 *
 * @return array<int, array<string, mixed>>
 */
function postGallery(int $postId): array
{
    $ids = getPostMeta($postId, 'gallery', []);

    if (!is_array($ids) || empty($ids)) {
        return [];
    }

    $items = [];

    foreach (array_slice($ids, 0, 100) as $id) {
        $item = getMediaItem((int) $id);

        if ($item !== null && $item['is_image']) {
            $items[] = $item;
        }
    }

    return $items;
}

/**
 * ویدیوی یک نوشته، در قالبی که مستقیم قابل نمایش باشد
 *
 * @return array{type:string, embed?:string, src?:string, poster:string}|null
 */
function postVideo(int $postId): ?array
{
    $provider = (string) getPostMeta($postId, 'video_provider', '');
    $poster = (string) getPostMeta($postId, 'video_poster', '');

    if ($provider === 'aparat') {
        $id = aparatVideoId((string) getPostMeta($postId, 'video_aparat', ''));

        return $id === '' ? null
            : ['type' => 'aparat', 'embed' => aparatEmbedUrl($id), 'poster' => $poster];
    }

    if ($provider === 'file') {
        $src = (string) getPostMeta($postId, 'video_file', '');

        return $src === '' ? null : ['type' => 'file', 'src' => $src, 'poster' => $poster];
    }

    return null;
}
