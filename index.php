<?php
/**
 * کنترلر اصلی سایت عمومی
 *
 * تمام درخواست‌های سایت (به جز فایل‌های ثابت و پنل مدیریت) از اینجا
 * عبور می‌کنند و به قالب مناسب هدایت می‌شوند.
 */

require_once __DIR__ . '/config.php';

securityHeaders();

// ─── بررسی وضعیت نصب و دسترسی به دیتابیس ───────────────────
$installState = Database::installState();

if ($installState === 'not_installed') {
    if (is_file(__DIR__ . '/setup.php')) {
        header('Location: ' . siteUrl('setup.php'));
        exit;
    }

    http_response_code(503);
    exit('فرد سی‌ام‌اس نصب نشده است و فایل نصب‌کننده در دسترس نیست.');
}

if ($installState === 'unavailable') {
    // قطعی دیتابیس یک خرابی موقت است، نه نبودِ نصب؛ بازدیدکننده هرگز
    // نباید در این حالت به نصب‌کننده هدایت شود
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/html; charset=utf-8');

    exit('<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>خطای موقت سرور</title></head>'
        . '<body style="font-family:Tahoma,sans-serif;background:#0b0b12;color:#f2f2f7;'
        . 'min-height:100vh;display:grid;place-items:center;margin:0;padding:24px;text-align:center">'
        . '<div><h1 style="font-size:22px;margin:0 0 12px">خطای موقت سرور</h1>'
        . '<p style="color:rgba(255,255,255,.6);line-height:2;margin:0">'
        . 'ارتباط با پایگاه داده برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید.</p></div>'
        . '</body></html>');
}

// ─── حالت تعمیر و نگهداری ──────────────────────────────────
if (isMaintenanceMode()) {
    http_response_code(503);
    header('Retry-After: 3600');

    renderTemplate('maintenance', [
        'page_title' => 'به‌زودی برمی‌گردیم',
        'no_index'   => true,
    ]);
}

// ─── استخراج مسیر درخواست ──────────────────────────────────
//
// دو حالت پشتیبانی می‌شود:
//   ۱) نشانی تمیز، مثل /blog/سلام  (نیازمند mod_rewrite)
//   ۲) نشانی پرسمانی، مثل index.php?route=blog/سلام
//
// حالت دوم برای هاست‌هایی است که .htaccess را نادیده می‌گیرند یا
// mod_rewrite ندارند؛ بدون آن تمام پیوندهای داخلی خطای ۴۰۴ می‌گیرند.
if (isset($_GET['route'])) {
    $requestPath = '/' . trim((string) $_GET['route'], '/');
} else {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    // حذف مسیر پوشه نصب، اگر سایت در زیرشاخه اجرا می‌شود
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

    if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
        $requestPath = substr($requestPath, strlen($basePath));
    }

    // اگر درخواست مستقیم به index.php باشد، بخش اسکریپت از مسیر حذف می‌شود
    $requestPath = preg_replace('#^/index\.php#', '', $requestPath) ?? $requestPath;
}

$segments = array_values(array_filter(explode('/', trim($requestPath, '/')), fn($s) => $s !== ''));
$segments = array_map('urldecode', $segments);

$page = max(1, (int) toLatinDigits((string) ($_GET['page'] ?? 1)));
$perPage = max(1, min(50, (int) getOption('posts_per_page', 10)));

// ─── مسیریابی ──────────────────────────────────────────────
$route = $segments[0] ?? '';

switch ($route) {
    // ─── صفحه اصلی ─────────────────────────────────────────
    case '':
        showFrontPage($page, $perPage);
        break;

    // ─── فهرست وبلاگ و نوشته تکی ───────────────────────────
    case 'blog':
        if (isset($segments[1])) {
            showSinglePost($segments[1]);
        } else {
            showBlogIndex($page, $perPage);
        }
        break;

    // ─── آرشیو دسته‌بندی ───────────────────────────────────
    case 'category':
        isset($segments[1]) ? showTermArchive($segments[1], 'category', $page, $perPage) : renderNotFound();
        break;

    // ─── آرشیو برچسب ───────────────────────────────────────
    case 'tag':
        isset($segments[1]) ? showTermArchive($segments[1], 'tag', $page, $perPage) : renderNotFound();
        break;

    // ─── آرشیو نویسنده ─────────────────────────────────────
    case 'author':
        isset($segments[1]) ? showAuthorArchive($segments[1], $page, $perPage) : renderNotFound();
        break;

    // ─── جستجو ─────────────────────────────────────────────
    case 'search':
        showSearch($page, $perPage);
        break;

    // ─── خوراک RSS ─────────────────────────────────────────
    case 'feed':
        showFeed();
        break;

    // ─── نقشه سایت ─────────────────────────────────────────
    case 'sitemap.xml':
        showSitemap();
        break;

    // ─── robots.txt ────────────────────────────────────────
    case 'robots.txt':
        showRobots();
        break;

    // ─── کاوه تشخیص مسیریابی ───────────────────────────────
    // فقط یک رشته ثابت برمی‌گرداند تا diagnose.php بفهمد آیا نشانی
    // تمیز روی این سرور کار می‌کند یا نه. هیچ داده‌ای افشا نمی‌کند.
    case '__fardcms_probe':
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        exit('FARDCMS_ROUTING_OK');

    // ─── در غیر این صورت: یک برگه با این نامک ──────────────
    default:
        // مسیرهای تودرتوی برگه‌ها با آخرین بخش آدرس تطبیق داده می‌شوند
        showPage(end($segments) ?: $route);
        break;
}

// ═══════════════════════════════════════════════════════════
//  کنترلرهای صفحه
// ═══════════════════════════════════════════════════════════

/**
 * صفحه اصلی — یا فهرست نوشته‌ها یا یک برگه ثابت
 */
function showFrontPage(int $page, int $perPage): void
{
    $frontPage = (string) getOption('front_page', 'blog');

    // اگر یک برگه به‌عنوان صفحه اصلی تعیین شده باشد
    if ($frontPage !== 'blog' && ctype_digit($frontPage)) {
        $post = getPost((int) $frontPage);

        if ($post !== null && $post['status'] === 'publish' && $post['type'] === 'page') {
            renderTemplate('page', [
                'post'             => $post,
                'page_title'       => $post['title'],
                'meta_description' => $post['excerpt'],
                'meta_image'       => $post['featured_image'],
                'canonical'        => routeUrl(''),
                'is_front'         => true,
                'is_single'        => true,
            ]);
        }
    }

    showBlogIndex($page, $perPage, true);
}

/**
 * فهرست نوشته‌های وبلاگ
 */
function showBlogIndex(int $page, int $perPage, bool $isFront = false): void
{
    $result = getPosts([
        'type'       => 'post',
        'status'     => 'publish',
        'page'       => $page,
        'per_page'   => $perPage,
        'with_terms' => true,
    ]);

    // درخواست صفحه‌ای که وجود ندارد باید ۴۰۴ بدهد، نه فهرست خالی
    if ($page > 1 && empty($result['items'])) {
        renderNotFound();
    }

    $settings = getSiteSettings();

    renderTemplate('index', [
        'posts'            => $result['items'],
        'meta'             => $result['meta'],
        'base_url'         => $isFront ? routeUrl('') : routeUrl('blog'),
        'page_title'       => $isFront ? '' : 'وبلاگ',
        'meta_description' => $settings['site_description'],
        'archive_title'    => $isFront ? $settings['site_tagline'] : 'آخرین نوشته‌ها',
        'is_front'         => $isFront,
        'is_archive'       => true,
        'no_index'         => $page > 1,
    ]);
}

/**
 * نمایش یک نوشته
 */
function showSinglePost(string $slug): void
{
    $post = getPostBySlug($slug, 'post');

    if ($post === null) {
        // نوشته خصوصی فقط برای کاربران مجاز نمایش داده می‌شود
        $private = getPostBySlug($slug, 'post', ['private']);

        if ($private === null || !currentUserCan('read_private_posts')) {
            renderNotFound();
        }

        $post = $private;
    }

    // شمارش بازدید — بازدید خود نویسنده شمرده نمی‌شود
    if (currentUserId() !== (int) ($post['author_id'] ?? 0)) {
        incrementPostViews((int) $post['id']);
    }

    $comments = getPostComments((int) $post['id']);

    renderTemplate('single', [
        'post'             => $post,
        'comments'         => $comments,
        'comment_count'    => countCommentTree($comments),
        'prev_post'        => getAdjacentPost($post, 'prev'),
        'next_post'        => getAdjacentPost($post, 'next'),
        'related'          => getRelatedPosts($post),
        'page_title'       => $post['title'],
        'meta_description' => $post['excerpt'],
        'meta_image'       => $post['featured_image'],
        'canonical'        => postUrl($post),
        'is_single'        => true,
        'no_index'         => $post['status'] !== 'publish',
    ]);
}

/**
 * نمایش یک برگه
 */
function showPage(string $slug): void
{
    $post = getPostBySlug($slug, 'page');

    if ($post === null) {
        renderNotFound();
    }

    // برگه‌ها می‌توانند قالب اختصاصی داشته باشند
    $template = !empty($post['template']) && preg_match('/^[a-z0-9_-]+$/i', $post['template'])
        ? 'page-' . $post['template']
        : 'page';

    $comments = $post['comment_status'] === 'open'
        ? getPostComments((int) $post['id'])
        : [];

    renderTemplate($template, [
        'post'             => $post,
        'comments'         => $comments,
        'comment_count'    => countCommentTree($comments),
        'children'         => getPosts([
            'type'     => 'page',
            'status'   => 'publish',
            'parent'   => (int) $post['id'],
            'per_page' => 50,
            'orderby'  => 'order',
            'order'    => 'ASC',
        ])['items'],
        'page_title'       => $post['title'],
        'meta_description' => $post['excerpt'],
        'meta_image'       => $post['featured_image'],
        'canonical'        => postUrl($post),
        'is_page'          => true,
        'is_single'        => true,
    ]);
}

/**
 * آرشیو دسته‌بندی یا برچسب
 */
function showTermArchive(string $slug, string $taxonomy, int $page, int $perPage): void
{
    $term = getTermBySlug($slug, $taxonomy);

    if ($term === null) {
        renderNotFound();
    }

    $result = getPosts([
        'type'       => 'post',
        'status'     => 'publish',
        'term'       => (int) $term['id'],
        'page'       => $page,
        'per_page'   => $perPage,
        'with_terms' => true,
    ]);

    if ($page > 1 && empty($result['items'])) {
        renderNotFound();
    }

    $label = $taxonomy === 'category' ? 'دسته' : 'برچسب';
    $base = routeUrl(($taxonomy === 'category' ? 'category/' : 'tag/') . $term['slug']);

    renderTemplate('archive', [
        'posts'            => $result['items'],
        'meta'             => $result['meta'],
        'term'             => $term,
        'base_url'         => $base,
        'page_title'       => $term['name'],
        'archive_title'    => "$label: {$term['name']}",
        'archive_subtitle' => $term['description'],
        'meta_description' => $term['description'] !== ''
            ? $term['description']
            : "نوشته‌های $label {$term['name']}",
        'canonical'        => $base,
        'is_archive'       => true,
        'no_index'         => $page > 1,
    ]);
}

/**
 * آرشیو نوشته‌های یک نویسنده
 */
function showAuthorArchive(string $username, int $page, int $perPage): void
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, username, bio, avatar FROM ' . tbl('users') . '
         WHERE username = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$username]);
    $author = $stmt->fetch();

    if (!$author) {
        renderNotFound();
    }

    $result = getPosts([
        'type'       => 'post',
        'status'     => 'publish',
        'author'     => (int) $author['id'],
        'page'       => $page,
        'per_page'   => $perPage,
        'with_terms' => true,
    ]);

    if ($page > 1 && empty($result['items'])) {
        renderNotFound();
    }

    $base = routeUrl('author/' . $author['username']);

    renderTemplate('archive', [
        'posts'            => $result['items'],
        'meta'             => $result['meta'],
        'author'           => $author,
        'base_url'         => $base,
        'page_title'       => $author['name'],
        'archive_title'    => 'نوشته‌های ' . $author['name'],
        'archive_subtitle' => $author['bio'],
        'meta_description' => $author['bio'] !== '' ? $author['bio'] : 'نوشته‌های ' . $author['name'],
        'canonical'        => $base,
        'is_archive'       => true,
        'no_index'         => $page > 1,
    ]);
}

/**
 * نتایج جستجو
 */
function showSearch(int $page, int $perPage): void
{
    $query = trim((string) ($_GET['q'] ?? ''));

    $result = $query !== ''
        ? getPosts([
            'type'       => 'post',
            'status'     => 'publish',
            'search'     => $query,
            'page'       => $page,
            'per_page'   => $perPage,
            'with_terms' => true,
        ])
        : ['items' => [], 'meta' => paginationMeta(0, 1, $perPage)];

    renderTemplate('search', [
        'posts'         => $result['items'],
        'meta'          => $result['meta'],
        'query'         => $query,
        'base_url'      => routeUrl('search', ['q' => $query]),
        'page_title'    => $query !== '' ? "جستجو برای «$query»" : 'جستجو',
        'archive_title' => $query !== ''
            ? 'نتایج جستجو برای «' . $query . '»'
            : 'جستجو در سایت',
        'is_search'     => true,
        'is_archive'    => true,
        // صفحه‌های نتیجه جستجو نباید در موتورهای جستجو ایندکس شوند
        'no_index'      => true,
    ]);
}

/**
 * خوراک RSS آخرین نوشته‌ها
 */
function showFeed(): void
{
    $settings = getSiteSettings();
    $posts = getPosts(['type' => 'post', 'status' => 'publish', 'per_page' => 20])['items'];

    header('Content-Type: application/rss+xml; charset=utf-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title><?= e($settings['site_title']) ?></title>
    <link><?= e(routeUrl('')) ?></link>
    <description><?= e($settings['site_description']) ?></description>
    <language>fa-IR</language>
    <lastBuildDate><?= e(date('r')) ?></lastBuildDate>
    <atom:link href="<?= e(routeUrl('feed')) ?>" rel="self" type="application/rss+xml"/>
    <?php foreach ($posts as $post): ?>
    <item>
      <title><?= e($post['title']) ?></title>
      <link><?= e(postUrl($post)) ?></link>
      <guid isPermaLink="true"><?= e(postUrl($post)) ?></guid>
      <pubDate><?= e(date('r', strtotime($post['published_at'] ?? $post['created_at']))) ?></pubDate>
      <?php if (!empty($post['author_name'])): ?>
      <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/"><?= e($post['author_name']) ?></dc:creator>
      <?php endif; ?>
      <description><?= e($post['excerpt']) ?></description>
    </item>
    <?php endforeach; ?>
  </channel>
</rss>
    <?php
    exit;
}

/**
 * نقشه سایت XML
 */
function showSitemap(): void
{
    $db = Database::getConnection();

    $rows = $db->query(
        'SELECT slug, type, updated_at FROM ' . tbl('posts') . "
         WHERE status = 'publish' AND type IN ('post', 'page')
         ORDER BY updated_at DESC
         LIMIT 5000"
    )->fetchAll();

    $terms = $db->query(
        'SELECT slug, taxonomy FROM ' . tbl('terms') . ' WHERE count > 0'
    )->fetchAll();

    header('Content-Type: application/xml; charset=utf-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    // صفحه اصلی
    echo "  <url><loc>" . e(routeUrl('')) . "</loc><changefreq>daily</changefreq>"
       . "<priority>1.0</priority></url>\n";
    echo "  <url><loc>" . e(routeUrl('blog')) . "</loc><changefreq>daily</changefreq>"
       . "<priority>0.9</priority></url>\n";

    foreach ($rows as $row) {
        $url = routeUrl(($row['type'] === 'page' ? '' : 'blog/') . $row['slug']);

        echo '  <url><loc>' . e($url) . '</loc>'
           . '<lastmod>' . e(date('Y-m-d', strtotime($row['updated_at']))) . '</lastmod>'
           . '<priority>' . ($row['type'] === 'page' ? '0.7' : '0.8') . "</priority></url>\n";
    }

    foreach ($terms as $term) {
        $url = routeUrl(($term['taxonomy'] === 'category' ? 'category/' : 'tag/') . $term['slug']);

        echo '  <url><loc>' . e($url) . '</loc><changefreq>weekly</changefreq>'
           . "<priority>0.6</priority></url>\n";
    }

    echo '</urlset>';
    exit;
}

/**
 * تولید robots.txt
 *
 * به‌صورت پویا ساخته می‌شود چون استاندارد robots.txt برای دستور Sitemap
 * آدرس مطلق می‌خواهد و آدرس سایت از تنظیمات می‌آید. در حالت تعمیر و
 * نگهداری هم خزیدن موتورهای جستجو متوقف می‌شود.
 */
function showRobots(): void
{
    header('Content-Type: text/plain; charset=utf-8');

    if (getOption('maintenance_mode', false)) {
        echo "User-agent: *\nDisallow: /\n";
        exit;
    }

    echo "User-agent: *\n";
    echo "Allow: /\n\n";
    echo "# پنل مدیریت، نقاط پایانی API و صفحه‌های احراز هویت ایندکس نمی‌شوند\n";

    foreach (['/admin/', '/api/', '/search', '/login.php', '/register.php',
              '/forgot-password.php', '/reset-password.php', '/setup.php'] as $path) {
        echo "Disallow: $path\n";
    }

    echo "\nSitemap: " . routeUrl('sitemap.xml') . "\n";
    exit;
}

/**
 * نوشته‌های مرتبط بر اساس دسته‌های مشترک
 *
 * @return array<int, array<string, mixed>>
 */
function getRelatedPosts(array $post, int $limit = 3): array
{
    $categoryIds = array_map(fn($c) => (int) $c['id'], $post['categories'] ?? []);

    if (empty($categoryIds)) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($categoryIds), '?'));

    $stmt = Database::getConnection()->prepare(
        'SELECT DISTINCT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.type,
                p.published_at, p.created_at
         FROM ' . tbl('posts') . ' p
         INNER JOIN ' . tbl('term_relationships') . " tr ON tr.post_id = p.id
         WHERE tr.term_id IN ($placeholders)
           AND p.id <> ?
           AND p.status = 'publish'
           AND p.type = 'post'
         ORDER BY COALESCE(p.published_at, p.created_at) DESC
         LIMIT $limit"
    );
    $stmt->execute([...$categoryIds, (int) $post['id']]);

    return array_map('formatPostRow', $stmt->fetchAll());
}
