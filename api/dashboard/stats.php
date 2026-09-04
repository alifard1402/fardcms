<?php
/**
 * GET /api/dashboard/stats.php
 * آمار و خلاصه وضعیت سایت برای صفحه اصلی پنل مدیریت
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('edit_posts');

$db = Database::getConnection();

$postCounts = getPostCounts('post');
$pageCounts = getPostCounts('page');

// ─── کارت‌های آماری ────────────────────────────────────────
$stats = [
    'posts'    => $postCounts['all'],
    'pages'    => $pageCounts['all'],
    'drafts'   => $postCounts['draft'] + $pageCounts['draft'],
    'comments' => 0,
    'pending_comments' => 0,
    'media'    => 0,
    'users'    => 0,
    'views'    => 0,
];

if (currentUserCan('moderate_comments')) {
    $commentCounts = getCommentCounts();
    $stats['comments'] = $commentCounts['approved'];
    $stats['pending_comments'] = $commentCounts['pending'];
}

if (currentUserCan('manage_users')) {
    $stats['users'] = getUserCounts()['all'];
}

$stats['media'] = (int) $db->query('SELECT COUNT(*) AS t FROM ' . tbl('media'))->fetch()['t'];
$stats['views'] = (int) $db->query('SELECT COALESCE(SUM(views), 0) AS t FROM ' . tbl('posts'))->fetch()['t'];

// ─── آخرین نوشته‌ها ────────────────────────────────────────
$recentArgs = ['type' => 'post', 'status' => 'any', 'per_page' => 5, 'orderby' => 'modified'];

if (!currentUserCan('edit_others_posts')) {
    $recentArgs['author'] = currentUserId();
}

// ─── پربازدیدترین نوشته‌ها ─────────────────────────────────
$popular = getPosts(['type' => 'post', 'status' => 'publish', 'per_page' => 5, 'orderby' => 'views'])['items'];

// ─── نمودار انتشار ۳۰ روز گذشته ────────────────────────────
$chartRows = $db->query(
    'SELECT DATE(COALESCE(published_at, created_at)) AS day, COUNT(*) AS total
     FROM ' . tbl('posts') . "
     WHERE status = 'publish'
       AND COALESCE(published_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY day
     ORDER BY day ASC"
)->fetchAll();

$byDay = [];
foreach ($chartRows as $row) {
    $byDay[$row['day']] = (int) $row['total'];
}

// تمام ۳۰ روز باید در نمودار باشند، حتی روزهای بدون نوشته
$chart = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart[] = [
        'date'   => $date,
        'label'  => jalaliDate('j F', $date),
        'short'  => jalaliDate('j/n', $date),
        'count'  => $byDay[$date] ?? 0,
    ];
}

$response = [
    'stats'   => $stats,
    'recent'  => getPosts($recentArgs)['items'],
    'popular' => $popular,
    'chart'   => $chart,
    'system'  => [
        'php_version'  => PHP_VERSION,
        'cms_version'  => FARDCMS_VERSION,
        'active_theme' => (string) getOption('active_theme', 'default'),
        'site_url'     => siteUrl(''),
    ],
];

// ─── دیدگاه‌های در انتظار و گزارش فعالیت ───────────────────
if (currentUserCan('moderate_comments')) {
    $response['pending_comment_list'] = getComments(['status' => 'pending', 'per_page' => 5])['items'];
}

if (currentUserCan('view_activity')) {
    $response['activity'] = getActivityLog(1, 8)['items'];
}

jsonSuccess($response);
