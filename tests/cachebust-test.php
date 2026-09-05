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
 * صفحه‌های PHP مهر را خودکار می‌گیرند، اما فایل‌های HTML ثابت (پنل
 * مدیریت و صفحه‌های ورود) مقدار را درون خود دارند و باید با نسخه فعلی
 * هم‌خوان بمانند. این تست همان هم‌خوانی را می‌سنجد.
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

echo "═══ صفحه‌های HTML ثابت ═══\n";

$htmlFiles = [
    'login.html',
    'register.html',
    'forgot-password.html',
    'reset-password.html',
    'admin/index.html',
];

foreach ($htmlFiles as $file) {
    $path = dirname(__DIR__) . '/' . $file;

    if (!is_file($path)) {
        bad($file, 'فایل یافت نشد');
        continue;
    }

    $html = (string) file_get_contents($path);

    // هر ارجاع به css یا js باید مهر نسخه داشته باشد
    preg_match_all('/(?:href|src)="([^"]+\.(?:css|js))(\?[^"]*)?"/i', $html, $matches, PREG_SET_ORDER);

    $unstamped = [];
    $stale = [];

    foreach ($matches as $m) {
        $query = $m[2] ?? '';

        if ($query === '') {
            $unstamped[] = $m[1];
        } elseif (!str_contains($query, "v=$version")) {
            $stale[] = $m[1] . $query;
        }
    }

    if (empty($matches)) {
        bad($file, 'هیچ ارجاع css/js پیدا نشد');
    } elseif (!empty($unstamped)) {
        bad($file, 'بدون مهر نسخه: ' . implode(', ', $unstamped));
    } elseif (!empty($stale)) {
        bad($file, 'مهر نسخه قدیمی: ' . implode(', ', $stale));
    } else {
        ok(sprintf('%-24s %d ارجاع، همه با v=%s', $file, count($matches), $version));
    }
}

echo "\n═══ آدرس‌های تولیدشده توسط PHP ═══\n";

$generated = [
    'assetUrl — css'  => assetUrl('assets/css/fonts.css'),
    'themeUrl — css'  => themeUrl('assets/style.css'),
    'themeUrl — js'   => themeUrl('assets/theme.js'),
];

foreach ($generated as $label => $url) {
    str_contains($url, "v=$version")
        ? ok("$label → $url")
        : bad($label, $url);
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

echo "\n═══ قواعد کش سرور ═══\n";

$htaccess = (string) @file_get_contents(dirname(__DIR__) . '/.htaccess');

str_contains($htaccess, 'no-cache, must-revalidate')
    ? ok('ماژول‌های js در .htaccess بازبینی می‌شوند')
    : bad('.htaccess', 'قاعده بازبینی js وجود ندارد');

$nginx = (string) @file_get_contents(dirname(__DIR__) . '/nginx.conf.example');

str_contains($nginx, 'no-cache, must-revalidate')
    ? ok('ماژول‌های js در نمونه nginx بازبینی می‌شوند')
    : bad('nginx.conf.example', 'قاعده بازبینی js وجود ندارد');

echo "\n════ PASS: $pass  FAIL: $fail ════\n";

if ($fail > 0) {
    echo "\nپس از تغییر FARDCMS_VERSION، مهر نسخه در فایل‌های HTML ثابت را\n"
       . "هم به‌روز کنید:\n"
       . "  grep -rl '?v=' *.html admin/index.html | xargs sed -i 's/?v=[0-9.]*/?v=$version/g'\n";
}

exit($fail > 0 ? 1 : 0);
