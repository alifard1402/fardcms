<?php
/**
 * تست مهر نسخه روی فایل‌های ظاهری
 *
 * اجرا:  php tests/cachebust-test.php
 *
 * چرا لازم است: CSS و JS برای سرعت مدت طولانی کش می‌شوند. اگر آدرسشان
 * مهر نسخه نداشته باشد، هر به‌روزرسانی ظاهری تا پایان مدت کش به کاربر
 * نمی‌رسد و او صفحه‌ای نیمه‌خراب می‌بیند بدون آنکه بداند چرا.
 *
 * مهر از زمان تغییر خود فایل ساخته می‌شود، نه از شماره نسخه دستی: هر
 * فایلی که آپلود شود بلافاصله آدرس تازه می‌گیرد. این تست می‌سنجد که
 * صفحه‌های ورودی واقعاً همین عدد را چاپ می‌کنند و مسیرشان درست نگاشت
 * شده است — نگاشت اشتباه بی‌سروصدا مهر را به فایل دیگری گره می‌زند.
 *
 * به سرور توسعه روی 127.0.0.1:8765 نیاز دارد.
 */

require dirname(__DIR__) . '/config.php';

$pass = 0;
$fail = 0;

function ok(string $label): void
{
    global $pass;
    printf("  PASS  %s\n", $label);
    $pass++;
}

function bad(string $label, string $detail = ''): void
{
    global $fail;
    printf("  FAIL  %s%s\n", $label, $detail !== '' ? " — $detail" : '');
    $fail++;
}

$version = FARDCMS_VERSION;
echo "نسخه فعلی: $version\n\n";

echo "═══ صفحه‌های ورودی (PHP) ═══\n";

$BASE = getenv('FARDCMS_TEST_URL') ?: 'http://127.0.0.1:8765';
$root = dirname(__DIR__);

$entryPages = [
    'login.php',
    'register.php',
    'forgot-password.php',
    'reset-password.php',
    'admin/index.php',
];

foreach ($entryPages as $page) {
    $path = "$root/$page";

    if (!is_file($path)) {
        bad($page, 'فایل یافت نشد');
        continue;
    }

    // صفحه باید مهر را با assetStamp بسازد، نه با عدد ثابت
    $source = (string) file_get_contents($path);

    str_contains($source, 'assetStamp(')
        ? ok("$page — مهر را پویا می‌سازد")
        : bad($page, 'assetStamp صدا زده نمی‌شود؛ مهر ثابت است');

    preg_match('/\?v=[\d.]+"/', $source)
        && bad($page, 'مهر دست‌نویس در کد مانده است');

    // و مهم‌تر: خروجی واقعی صفحه
    $html = @file_get_contents("$BASE/$page");

    if ($html === false) {
        bad($page, "سرور توسعه پاسخ نداد ($BASE)");
        continue;
    }

    if (str_contains($html, '<?')) {
        bad($page, 'کد PHP اجرا نشده و خام چاپ شده است');
        continue;
    }

    preg_match_all('/(?:href|src)="([^"]+\.(?:css|js))\?v=([^"]+)"/i', $html, $refs, PREG_SET_ORDER);

    if (empty($refs)) {
        bad($page, 'هیچ ارجاع مهرخورده‌ای در خروجی نیست');
        continue;
    }

    $wrong = [];

    foreach ($refs as [$_, $href, $stamp]) {
        // آدرس نسبی را نسبت به پوشه همان صفحه به مسیر واقعی برمی‌گردانیم
        $target = realpath(dirname($path) . '/' . $href);

        if ($target === false || !str_starts_with($target, $root)) {
            $wrong[] = "$href → فایلی وجود ندارد";
            continue;
        }

        if ($stamp !== (string) filemtime($target)) {
            $wrong[] = "$href → مهر $stamp ≠ زمان فایل " . filemtime($target);
        }
    }

    empty($wrong)
        ? ok(sprintf('%-18s %d ارجاع، همه با زمان تغییر خودِ فایل', $page, count($refs)))
        : bad($page, implode(' | ', $wrong));
}

// نشانی‌های قدیمی html باید همچنان به همان صفحه برسند: پیوند بازیابی رمز
// در ایمیل‌های ارسال‌شده با نام قدیمی ثبت شده است.
$legacy = [
    'login.html'            => 'login.php',
    'register.html'         => 'register.php',
    'forgot-password.html'  => 'forgot-password.php',
    'reset-password.html'   => 'reset-password.php',
    'admin/index.html'      => 'index.php',
];

foreach ($legacy as $old => $new) {
    $html = @file_get_contents("$BASE/$old");

    $html !== false && !str_contains((string) $html, '<?')
        ? ok("نشانی قدیمی $old همچنان کار می‌کند")
        : bad("نشانی قدیمی $old", "به $new نمی‌رسد");

    // روی هاست‌هایی که .htaccess را نادیده می‌گیرند بازنویسی انجام
    // نمی‌شود و خودِ این فایل ارائه می‌شود؛ پس باید فایل واسط باشد و
    // پرسمان را هم منتقل کند (توکن بازیابی رمز در آدرس است).
    $stub = @file_get_contents("$root/$old");

    if ($stub === false) {
        bad("فایل واسط $old", 'وجود ندارد؛ روی هاست بدون mod_rewrite صفحه گم می‌شود');
        continue;
    }

    str_contains($stub, "location.replace('$new' + location.search + location.hash)")
        ? ok("فایل واسط $old به $new منتقل می‌کند")
        : bad("فایل واسط $old", "به $new منتقل نمی‌کند یا پرسمان را نگه نمی‌دارد");
}

// مهر باید با تغییر فایل عوض شود — همان چیزی که کل این سازوکار برای آن است
$probe = "$root/assets/css/fonts.css";
$before = assetStamp('assets/css/fonts.css');
$originalMtime = filemtime($probe);
touch($probe, $originalMtime + 60);
clearstatcache(true, $probe);

// حافظه‌ی داخلی assetStamp در همین اجرا پر شده، پس خروجی صفحه را می‌سنجیم
$html = (string) @file_get_contents("$BASE/login.php");
preg_match('/fonts\.css\?v=(\d+)/', $html, $m);
$after = $m[1] ?? '';

touch($probe, $originalMtime);

$after === (string) ($originalMtime + 60) && $after !== ltrim($before, '?v=')
    ? ok('با تغییر فایل، مهر هم عوض می‌شود')
    : bad('مهر پس از تغییر فایل', "پیش: $before، پس: $after");

echo "\n═══ آدرس‌های تولیدشده توسط PHP ═══\n";

$generated = [
    'assetUrl — css'  => ['url' => assetUrl('assets/css/fonts.css'),
                          'path' => 'assets/css/fonts.css'],
    'themeUrl — css'  => ['url' => themeUrl('assets/style.css'),
                          'path' => 'themes/default/assets/style.css'],
    'themeUrl — js'   => ['url' => themeUrl('assets/theme.js'),
                          'path' => 'themes/default/assets/theme.js'],
];

foreach ($generated as $label => $file) {
    $url = $file['url'];
    $expectedStamp = 'v=' . filemtime(dirname(__DIR__) . '/' . $file['path']);

    str_contains($url, $expectedStamp)
        ? ok("$label → $url")
        : bad($label, "$url — انتظار $expectedStamp");
}

// فایل‌هایی که نباید مهر بخورند
$unstampedExpected = [
    'تصویر' => assetUrl('assets/favicon.svg'),
    'مسیر'  => assetUrl('admin/'),
    'قلم'   => assetUrl('assets/fonts/Vazirmatn-Variable.woff2'),
];

foreach ($unstampedExpected as $label => $url) {
    !str_contains($url, 'v=')
        ? ok("$label بدون مهر → $url")
        : bad("$label نباید مهر بخورد", $url);
}

echo "\n═══ خودترمیمی استایل پنل ═══\n";
// اگر مرورگر یا CDN نسخه قدیمی admin.css را نگه دارد، app.js آن را
// تشخیص می‌دهد و دوباره می‌گیرد. این کار فقط وقتی درست است که شناسه
// داخل CSS و نسخه‌ای که app.js انتظار دارد با نسخه کد یکی باشند.
$adminCss = (string) @file_get_contents(dirname(__DIR__) . '/admin/assets/css/admin.css');
$appJs    = (string) @file_get_contents(dirname(__DIR__) . '/admin/assets/js/app.js');

preg_match('/--fardcms-css:\s*["\']([\d.]+)["\']/', $adminCss, $cssV);
preg_match('/EXPECTED_CSS_VERSION\s*=\s*[\'"]([\d.]+)[\'"]/', $appJs, $jsV);

($cssV[1] ?? '') === $version
    ? ok("شناسه --fardcms-css در admin.css برابر $version است")
    : bad('admin.css', 'شناسه نسخه: ' . ($cssV[1] ?? 'ندارد'));

($jsV[1] ?? '') === $version
    ? ok("EXPECTED_CSS_VERSION در app.js برابر $version است")
    : bad('app.js', 'نسخه موردانتظار: ' . ($jsV[1] ?? 'ندارد'));

str_contains($appJs, 'ensureFreshStylesheet()')
    ? ok('app.js استایل کهنه را دوباره می‌گیرد')
    : bad('app.js', 'فراخوانی ensureFreshStylesheet وجود ندارد');

echo "\n═══ قواعد کش سرور ═══\n";

$htaccess = (string) @file_get_contents(dirname(__DIR__) . '/.htaccess');

str_contains($htaccess, 'no-cache, must-revalidate')
    ? ok('ماژول‌های js در .htaccess بازبینی می‌شوند')
    : bad('.htaccess', 'قاعده بازبینی js وجود ندارد');

str_contains($htaccess, 'RewriteRule ^(login|register|forgot-password|reset-password)\\.html$')
    ? ok('نشانی‌های قدیمی html در .htaccess به PHP می‌رسند')
    : bad('.htaccess', 'قاعده سازگاری نشانی قدیمی وجود ندارد');

str_contains($htaccess, 'DirectoryIndex index.php index.html')
    ? ok('.htaccess صفحه index.php را مقدم می‌داند')
    : bad('.htaccess', 'DirectoryIndex تنظیم نشده؛ پنل قدیمی ارائه می‌شود');

$nginx = (string) @file_get_contents(dirname(__DIR__) . '/nginx.conf.example');

str_contains($nginx, 'no-cache, must-revalidate')
    ? ok('ماژول‌های js در نمونه nginx بازبینی می‌شوند')
    : bad('nginx.conf.example', 'قاعده بازبینی js وجود ندارد');

str_contains($nginx, 'forgot-password|reset-password)\\.html$')
    ? ok('نشانی‌های قدیمی html در نمونه nginx تغییرمسیر می‌گیرند')
    : bad('nginx.conf.example', 'قاعده سازگاری نشانی قدیمی وجود ندارد');

echo "\n════ PASS: $pass  FAIL: $fail ════\n";

if ($fail > 0) {
    echo "\nپس از تغییر FARDCMS_VERSION، مهر نسخه در فایل‌های HTML ثابت را\n"
       . "هم به‌روز کنید:\n"
       . "  grep -rl '?v=' *.html admin/index.php | xargs sed -i 's/?v=[0-9.]*/?v=$version/g'\n";
}

exit($fail > 0 ? 1 : 0);
