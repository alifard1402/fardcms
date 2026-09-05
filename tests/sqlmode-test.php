<?php
/**
 * تست سازگاری کوئری‌ها با حالت سخت‌گیرانه SQL
 *
 * اجرا:  php tests/sqlmode-test.php
 *
 * چرا لازم است: MySQL 5.7 و 8 به‌صورت پیش‌فرض ONLY_FULL_GROUP_BY را
 * روشن دارند، اما MariaDB (که در توسعه استفاده می‌شود) آن را خاموش
 * دارد. کوئری‌ای که در توسعه بی‌مشکل است می‌تواند روی هاست واقعی خطای
 * ۱۰۵۵ بدهد و صفحه را با «خطای داخلی سرور» از کار بیندازد.
 *
 * این تست حالت سخت‌گیرانه را فقط روی همین اتصال اعمال می‌کند، پس
 * تنظیمات سرور دست‌نخورده می‌ماند.
 */

require dirname(__DIR__) . '/config.php';

const STRICT_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,'
                  . 'NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

$db = Database::getConnection();
$original = $db->query('SELECT @@session.sql_mode')->fetchColumn();
$db->exec("SET SESSION sql_mode = '" . STRICT_MODE . "'");

echo "حالت SQL این اتصال:\n  " . $db->query('SELECT @@session.sql_mode')->fetchColumn() . "\n\n";

// نقش مدیر تا مسیرهایی که به دسترسی نیاز دارند هم اجرا شوند
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'administrator';
$_SESSION['user_name'] = 'تست';
$_SESSION['user_email'] = 'test@example.com';

$pass = 0;
$fail = 0;

/**
 * اجرای یک مسیر خواندن داده و گزارش خطای احتمالی SQL
 */
function probe(string $label, callable $fn): void
{
    global $pass, $fail;

    try {
        $fn();
        printf("  PASS  %s\n", $label);
        $pass++;
    } catch (Throwable $e) {
        printf("  FAIL  %s\n        %s\n", $label, substr($e->getMessage(), 0, 160));
        $fail++;
    }
}

echo "═══ خواندن محتوا ═══\n";
probe('getPosts — بدون فیلتر',        fn() => getPosts(['type' => 'post', 'status' => 'any']));
probe('getPosts — فیلتر دسته',        fn() => getPosts(['type' => 'post', 'term' => 1, 'with_terms' => true]));
probe('getPosts — جستجو',             fn() => getPosts(['type' => 'post', 'search' => 'تست']));
probe('getPosts — نویسنده',           fn() => getPosts(['type' => 'post', 'author' => 1]));
probe('getPosts — برگه‌های فرزند',    fn() => getPosts(['type' => 'page', 'parent' => 1]));
probe('getPosts — مرتب‌سازی بازدید',  fn() => getPosts(['type' => 'post', 'orderby' => 'views']));
probe('getPostCounts',                fn() => getPostCounts('post'));

$anyPost = getPosts(['type' => 'post', 'status' => 'any', 'per_page' => 1])['items'][0] ?? null;

if ($anyPost !== null) {
    probe('getPost',            fn() => getPost((int) $anyPost['id']));
    probe('getPostBySlug',      fn() => getPostBySlug((string) $anyPost['slug'], 'post', ['publish', 'draft']));
    probe('getAdjacentPost',    fn() => getAdjacentPost($anyPost, 'next'));
    probe('getAllPostMeta',     fn() => getAllPostMeta((int) $anyPost['id']));
    probe('getPostComments',    fn() => getPostComments((int) $anyPost['id']));
}

echo "\n═══ طبقه‌بندی، رسانه و دیدگاه ═══\n";
probe('getTerms — دسته',      fn() => getTerms('category'));
probe('getTerms — برچسب',     fn() => getTerms('tag'));
probe('getMediaList',         fn() => getMediaList(['per_page' => 5]));
probe('getMediaList — جستجو', fn() => getMediaList(['search' => 'x']));
probe('getComments',          fn() => getComments(['per_page' => 5]));
probe('getComments — جستجو',  fn() => getComments(['search' => 'x']));
probe('getCommentCounts',     fn() => getCommentCounts());

echo "\n═══ کاربران، فهرست‌ها و گزارش ═══\n";
probe('getUsers',        fn() => getUsers(['per_page' => 5]));
probe('getUsers — جستجو',fn() => getUsers(['search' => 'admin']));
probe('getUserCounts',   fn() => getUserCounts());
probe('getUser',         fn() => getUser(1));
probe('getUserSessions', fn() => getUserSessions(1));
probe('getMenus',        fn() => getMenus());
probe('getMenuByLocation', fn() => getMenuByLocation('primary'));
probe('getActivityLog',  fn() => getActivityLog(1, 10));

echo "\n═══ کوئری‌های پیشخوان ═══\n";
probe('شمارش رسانه', fn() => Database::getConnection()
    ->query('SELECT COUNT(*) AS t FROM ' . tbl('media'))->fetch());
probe('مجموع بازدید', fn() => Database::getConnection()
    ->query('SELECT COALESCE(SUM(views), 0) AS t FROM ' . tbl('posts'))->fetch());
probe('نمودار ۳۰ روزه', fn() => Database::getConnection()->query(
    'SELECT DATE(COALESCE(published_at, created_at)) AS day, COUNT(*) AS total
     FROM ' . tbl('posts') . "
     WHERE status = 'publish'
       AND COALESCE(published_at, created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY day
     ORDER BY day ASC")->fetchAll());
probe('بازشماری ترم‌ها', fn() => recountAllTerms());

echo "\n═══ نوشتن داده ═══\n";
probe('savePost', function () {
    $r = savePost(['title' => 'آزمون حالت سخت‌گیرانه', 'content' => '<p>x</p>', 'status' => 'draft']);

    if (!$r['success']) {
        throw new RuntimeException($r['message']);
    }

    $GLOBALS['strict_test_post'] = (int) $r['data']['post']['id'];
});

if (!empty($GLOBALS['strict_test_post'])) {
    $id = $GLOBALS['strict_test_post'];
    probe('setPostStatus', fn() => setPostStatus($id, 'publish'));
    probe('syncPostTerms', fn() => syncPostTerms($id, [], ['برچسب آزمون']));
    probe('incrementPostViews', fn() => incrementPostViews($id));
    probe('deletePost', fn() => deletePost($id));
}

// بازگرداندن حالت اولیه اتصال
$db->exec("SET SESSION sql_mode = " . $db->quote($original));

echo "\n════ PASS: $pass  FAIL: $fail ════\n";

if ($fail > 0) {
    echo "\nخطای ۱۰۵۵ یعنی کوئری با ONLY_FULL_GROUP_BY سازگار نیست و روی\n"
       . "هاست‌هایی با MySQL 5.7/8 صفحه را با «خطای داخلی سرور» می‌شکند.\n";
}

exit($fail > 0 ? 1 : 0);
