<?php
/**
 * موتور قالب — بارگذاری و رندر قالب‌های سایت عمومی
 */

/**
 * داده‌های صفحه جاری که در تمام قالب‌ها در دسترس است
 *
 * @var array<string, mixed>
 */
$GLOBALS['fardcms_view'] = [];

/**
 * مسیر پوشه قالب فعال
 */
function activeThemePath(): string
{
    $theme = (string) getOption('active_theme', 'default');

    // نام قالب از دیتابیس می‌آید و باید پیش از ساخت مسیر پاک‌سازی شود
    if (!preg_match('/^[a-z0-9_-]+$/i', $theme)) {
        $theme = 'default';
    }

    $path = THEMES_PATH . '/' . $theme;

    return is_dir($path) ? $path : THEMES_PATH . '/default';
}

/**
 * آدرس فایل‌های قالب فعال
 *
 * ریشه‌نسبی است تا CSS و جاوااسکریپت قالب مستقل از نام میزبان بارگذاری شوند.
 */
function themeUrl(string $path = ''): string
{
    return assetUrl('themes/' . basename(activeThemePath()) . '/' . ltrim($path, '/'));
}

/**
 * تنظیم داده‌های صفحه جاری
 *
 * @param array<string, mixed> $data
 */
function setView(array $data): void
{
    $GLOBALS['fardcms_view'] = array_merge($GLOBALS['fardcms_view'], $data);
}

/**
 * خواندن یک مقدار از داده‌های صفحه جاری
 */
function view(string $key, mixed $default = null): mixed
{
    return $GLOBALS['fardcms_view'][$key] ?? $default;
}

/**
 * رندر یک قالب
 *
 * قالب‌ها به داده‌های صفحه از طریق تابع view() دسترسی دارند.
 *
 * @param string $template نام قالب بدون پسوند، مثلاً 'single'
 * @param array<string, mixed> $data داده‌های اضافه
 */
function renderTemplate(string $template, array $data = []): void
{
    setView($data);

    $file = activeThemePath() . '/' . basename($template) . '.php';

    if (!is_file($file)) {
        // اگر قالب اختصاصی نبود، به قالب پیش‌فرض برمی‌گردیم
        $file = activeThemePath() . '/index.php';
    }

    if (!is_file($file)) {
        http_response_code(500);
        echo 'قالب سایت یافت نشد.';
        exit;
    }

    require $file;
    exit;
}

/**
 * گنجاندن یک بخش قالب (سربرگ، پاورقی و ...)
 *
 * @param array<string, mixed> $data
 */
function themePart(string $part, array $data = []): void
{
    $file = activeThemePath() . '/' . basename($part) . '.php';

    if (!is_file($file)) {
        error_log("Theme part not found: $part");
        return;
    }

    // متغیرهای محلی این بخش، بدون آلوده کردن داده‌های صفحه
    extract($data, EXTR_SKIP);

    require $file;
}

/**
 * نمایش صفحه ۴۰۴
 */
function renderNotFound(): void
{
    http_response_code(404);

    renderTemplate('404', [
        'page_title' => 'صفحه یافت نشد',
        'is_404'     => true,
    ]);
}

/**
 * ساخت تگ‌های متای صفحه (SEO و شبکه‌های اجتماعی)
 */
function renderMetaTags(): void
{
    $settings = getSiteSettings();

    $title = (string) view('page_title', '');
    $siteTitle = (string) $settings['site_title'];

    $fullTitle = $title !== '' ? "$title — $siteTitle" : $siteTitle;

    $description = (string) view('meta_description', $settings['site_description']);
    $image = (string) view('meta_image', $settings['seo_meta_image']);
    $canonical = (string) view('canonical', currentUrl());
    $type = view('is_single') ? 'article' : 'website';

    echo '<title>' . e($fullTitle) . "</title>\n";
    echo '  <meta name="description" content="' . e(makeExcerpt($description, 160)) . "\">\n";
    echo '  <link rel="canonical" href="' . e($canonical) . "\">\n";

    // نتایج جستجو و صفحه‌های خصوصی نباید ایندکس شوند
    if (view('no_index')) {
        echo '  <meta name="robots" content="noindex, follow">' . "\n";
    }

    echo '  <meta property="og:type" content="' . e($type) . "\">\n";
    echo '  <meta property="og:title" content="' . e($fullTitle) . "\">\n";
    echo '  <meta property="og:description" content="' . e(makeExcerpt($description, 160)) . "\">\n";
    echo '  <meta property="og:url" content="' . e($canonical) . "\">\n";
    echo '  <meta property="og:site_name" content="' . e($siteTitle) . "\">\n";
    echo '  <meta property="og:locale" content="fa_IR">' . "\n";

    if ($image !== '') {
        echo '  <meta property="og:image" content="' . e($image) . "\">\n";
        echo '  <meta name="twitter:card" content="summary_large_image">' . "\n";
    } else {
        echo '  <meta name="twitter:card" content="summary">' . "\n";
    }

    // داده ساخت‌یافته برای نوشته‌ها
    if ($type === 'article' && ($post = view('post'))) {
        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => $post['title'],
            'description'   => makeExcerpt((string) $post['excerpt'], 160),
            'datePublished' => date('c', strtotime($post['published_at'] ?? $post['created_at'])),
            'dateModified'  => date('c', strtotime($post['updated_at'])),
            'author'        => ['@type' => 'Person', 'name' => $post['author_name'] ?? $siteTitle],
            'publisher'     => ['@type' => 'Organization', 'name' => $siteTitle],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        ];

        if (!empty($post['featured_image'])) {
            $schema['image'] = $post['featured_image'];
        }

        echo '  <script type="application/ld+json">'
           . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
           . "</script>\n";
    }
}

/**
 * آدرس کامل صفحه جاری
 */
function currentUrl(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    return rtrim(SITE_URL, '/') . $path;
}

/**
 * رندر یک فهرست ناوبری
 *
 * @param string $location جایگاه فهرست، مثلاً 'primary'
 */
function renderMenu(string $location, string $class = 'nav-menu'): void
{
    $items = getMenuByLocation($location);

    if (empty($items)) {
        return;
    }

    echo '<ul class="' . e($class) . '">';
    renderMenuItems($items);
    echo '</ul>';
}

/**
 * رندر بازگشتی آیتم‌های فهرست
 *
 * @param array<int, array<string, mixed>> $items
 */
function renderMenuItems(array $items): void
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    foreach ($items as $item) {
        $url = (string) $item['resolved_url'];
        $hasChildren = !empty($item['children']);

        // آیتم فعال بر اساس تطابق مسیر آدرس تعیین می‌شود
        $itemPath = parse_url($url, PHP_URL_PATH) ?: '';
        $isActive = $itemPath !== '' && rtrim($itemPath, '/') === rtrim($currentPath, '/');

        $classes = array_filter([
            $hasChildren ? 'has-children' : '',
            $isActive ? 'is-active' : '',
        ]);

        echo '<li' . ($classes ? ' class="' . e(implode(' ', $classes)) . '"' : '') . '>';
        echo '<a href="' . e($url) . '"';

        if ($item['target'] === '_blank') {
            echo ' target="_blank" rel="noopener"';
        }

        if ($isActive) {
            echo ' aria-current="page"';
        }

        echo '>' . e($item['title']) . '</a>';

        if ($hasChildren) {
            echo '<ul class="sub-menu">';
            renderMenuItems($item['children']);
            echo '</ul>';
        }

        echo '</li>';
    }
}

/**
 * رندر پیوندهای صفحه‌بندی
 *
 * @param array<string, int> $meta خروجی paginationMeta()
 * @param string $baseUrl آدرس پایه بدون پارامتر صفحه
 */
function renderPagination(array $meta, string $baseUrl): void
{
    $totalPages = (int) ($meta['total_pages'] ?? 0);
    $current = (int) ($meta['page'] ?? 1);

    if ($totalPages <= 1) {
        return;
    }

    // حفظ پارامترهای دیگر آدرس (مثلاً عبارت جستجو)
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    $link = fn(int $page) => $page <= 1 ? $baseUrl : $baseUrl . $separator . 'page=' . $page;

    echo '<nav class="pagination" aria-label="صفحه‌بندی">';

    if ($current > 1) {
        echo '<a class="page-link" href="' . e($link($current - 1)) . '" rel="prev">صفحه قبل</a>';
    }

    // پنجره‌ای از شماره صفحه‌ها اطراف صفحه جاری
    $from = max(1, $current - 2);
    $to = min($totalPages, $current + 2);

    if ($from > 1) {
        echo '<a class="page-link" href="' . e($link(1)) . '">' . toPersianDigits('1') . '</a>';

        if ($from > 2) {
            echo '<span class="page-gap">…</span>';
        }
    }

    for ($i = $from; $i <= $to; $i++) {
        if ($i === $current) {
            echo '<span class="page-link is-current" aria-current="page">'
               . toPersianDigits((string) $i) . '</span>';
        } else {
            echo '<a class="page-link" href="' . e($link($i)) . '">' . toPersianDigits((string) $i) . '</a>';
        }
    }

    if ($to < $totalPages) {
        if ($to < $totalPages - 1) {
            echo '<span class="page-gap">…</span>';
        }

        echo '<a class="page-link" href="' . e($link($totalPages)) . '">'
           . toPersianDigits((string) $totalPages) . '</a>';
    }

    if ($current < $totalPages) {
        echo '<a class="page-link" href="' . e($link($current + 1)) . '" rel="next">صفحه بعد</a>';
    }

    echo '</nav>';
}

/**
 * رندر بازگشتی درخت دیدگاه‌ها
 *
 * @param array<int, array<string, mixed>> $comments
 */
function renderComments(array $comments, int $depth = 0): void
{
    foreach ($comments as $comment) {
        $initials = mb_substr((string) $comment['author_name'], 0, 1, 'UTF-8');
        ?>
        <li class="comment" id="comment-<?= (int) $comment['id'] ?>">
          <article class="comment-body">
            <header class="comment-head">
              <?php if (!empty($comment['author_avatar'])): ?>
                <img class="comment-avatar" src="<?= e($comment['author_avatar']) ?>"
                     alt="" width="40" height="40" loading="lazy">
              <?php else: ?>
                <span class="comment-avatar comment-avatar--initial"><?= e($initials) ?></span>
              <?php endif; ?>

              <div>
                <span class="comment-author"><?= e($comment['author_name']) ?></span>
                <time class="comment-date" datetime="<?= e(date('c', strtotime($comment['created_at']))) ?>">
                  <?= e($comment['date_relative']) ?>
                </time>
              </div>
            </header>

            <div class="comment-text"><?= nl2br(e($comment['content'])) ?></div>

            <?php if ($depth < 2): ?>
              <button type="button" class="comment-reply-btn"
                      data-reply-to="<?= (int) $comment['id'] ?>"
                      data-reply-author="<?= e($comment['author_name']) ?>">
                پاسخ
              </button>
            <?php endif; ?>
          </article>

          <?php if (!empty($comment['replies'])): ?>
            <ul class="comment-children">
              <?php renderComments($comment['replies'], $depth + 1); ?>
            </ul>
          <?php endif; ?>
        </li>
        <?php
    }
}

/**
 * شمارش کل دیدگاه‌های یک درخت (شامل پاسخ‌ها)
 *
 * @param array<int, array<string, mixed>> $comments
 */
function countCommentTree(array $comments): int
{
    $total = 0;

    foreach ($comments as $comment) {
        $total += 1 + countCommentTree($comment['replies'] ?? []);
    }

    return $total;
}

/**
 * تصویر شاخص یا تصویر جایگزین یک نوشته
 */
function postThumbnail(array $post): string
{
    return (string) ($post['featured_image'] ?? '');
}

/**
 * آیا سایت در حالت تعمیر و نگهداری است؟
 *
 * مدیران همیشه به سایت دسترسی دارند تا بتوانند آن را آماده کنند.
 */
function isMaintenanceMode(): bool
{
    return (bool) getOption('maintenance_mode', false) && !currentUserCan('manage_settings');
}
