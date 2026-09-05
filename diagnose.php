<?php
/**
 * ابزار تشخیص مشکل فرد سی‌ام‌اس
 *
 * این فایل را کنار index.php بگذارید و در مرورگر باز کنید:
 *   https://example.com/fardcms/diagnose.php
 *
 * برای عیب‌یابی «صفحه سفید» و «برگه‌ها ۴۰۴ می‌دهند» ساخته شده است.
 * هیچ رمز یا داده حساسی نمایش نمی‌دهد، اما بعد از رفع مشکل حذفش کنید.
 */

// خطاها اینجا عمداً نمایش داده می‌شوند؛ کل هدف همین است
error_reporting(E_ALL);
ini_set('display_errors', '1');

$rows = [];
$problems = [];

/**
 * ثبت یک نتیجه بررسی
 *
 * @param 'ok'|'warn'|'fail'|'info' $state  حالت info صرفاً توضیحی است و
 *        نشانه مشکل نیست؛ در شمارش هشدارها هم نمی‌آید.
 */
function check(string $label, string $state, string $value, string $hint = ''): void
{
    global $rows, $problems;

    $rows[] = compact('label', 'state', 'value', 'hint');

    if ($state === 'fail') {
        $problems[] = $label . ($hint !== '' ? ' — ' . $hint : '');
    }
}

function h(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ═══════════════════════════════════════════════════════════
//  ۱. محیط PHP
// ═══════════════════════════════════════════════════════════
check(
    'نسخه PHP',
    PHP_VERSION_ID >= 80100 ? 'ok' : 'fail',
    PHP_VERSION,
    PHP_VERSION_ID >= 80100 ? '' : 'فرد سی‌ام‌اس به PHP 8.1 یا بالاتر نیاز دارد'
);

foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true,
          'fileinfo' => true, 'dom' => true, 'gd' => false] as $ext => $required) {
    $loaded = extension_loaded($ext);

    check(
        "افزونه $ext",
        $loaded ? 'ok' : ($required ? 'fail' : 'warn'),
        $loaded ? 'نصب است' : 'نصب نیست',
        $loaded ? '' : ($required ? 'بدون این افزونه سایت کار نمی‌کند' : 'اختیاری — برای ساخت تصویر بندانگشتی')
    );
}

// ═══════════════════════════════════════════════════════════
//  ۲. فایل‌های پروژه
// ═══════════════════════════════════════════════════════════
$configExists = is_file(__DIR__ . '/config.php');
check('فایل config.php', $configExists ? 'ok' : 'fail',
    $configExists ? 'موجود است' : 'یافت نشد',
    $configExists ? '' : 'این فایل باید کنار diagnose.php باشد');

if (!$configExists) {
    renderReport();
    exit;
}

// بارگذاری هسته؛ خطای احتمالی همین‌جا گرفته و نمایش داده می‌شود
try {
    require __DIR__ . '/config.php';
    check('بارگذاری هسته سیستم', 'ok', 'موفق');
} catch (Throwable $e) {
    check('بارگذاری هسته سیستم', 'fail', get_class($e),
        $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine());
    renderReport();
    exit;
}

foreach (['index.php', 'themes/default/index.php', 'themes/default/page.php',
          'themes/default/header.php', 'themes/default/footer.php',
          'assets/vendor/vue.global.prod.js', 'assets/fonts/Vazirmatn-Variable.woff2'] as $file) {
    $exists = is_file(__DIR__ . '/' . $file);

    check("فایل $file", $exists ? 'ok' : 'fail',
        $exists ? 'موجود است' : 'یافت نشد',
        $exists ? '' : 'آپلود کامل انجام نشده است');
}

foreach ([UPLOADS_PATH => 'uploads', STORAGE_PATH => 'storage'] as $path => $name) {
    $writable = is_dir($path) && is_writable($path);

    check("قابلیت نوشتن در $name", $writable ? 'ok' : 'fail',
        $writable ? 'قابل نوشتن' : (is_dir($path) ? 'قابل نوشتن نیست' : 'پوشه وجود ندارد'),
        $writable ? '' : 'دسترسی پوشه را روی 755 بگذارید');
}

// ═══════════════════════════════════════════════════════════
//  ۳. دیتابیس و وضعیت نصب
// ═══════════════════════════════════════════════════════════
check('میزبان دیتابیس', 'ok', DB_HOST . ' / ' . DB_NAME);
check('رمز دیتابیس', DB_PASS === '' ? 'warn' : 'ok',
    DB_PASS === '' ? 'خالی است' : 'تنظیم شده',
    DB_PASS === '' ? 'روی سرور واقعی، کاربر دیتابیس باید رمز داشته باشد' : '');

$state = Database::installState();

check('وضعیت نصب', $state === 'installed' ? 'ok' : 'fail',
    match ($state) {
        'installed'     => 'نصب‌شده',
        'not_installed' => 'نصب نشده',
        default         => 'دیتابیس در دسترس نیست',
    },
    match ($state) {
        'installed'     => '',
        'not_installed' => 'فایل setup.php را اجرا کنید',
        default         => 'تنظیمات DB_HOST، DB_NAME، DB_USER و DB_PASS در config.php را بررسی کنید',
    });

// ═══════════════════════════════════════════════════════════
//  ۴. آدرس سایت — شایع‌ترین خطای نصب در زیرشاخه
// ═══════════════════════════════════════════════════════════
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$detectedUrl = $scheme . '://' . $host . $dir;

$urlMatches = rtrim(SITE_URL, '/') === $detectedUrl;

check('آدرس سایت (SITE_URL)', $urlMatches ? 'ok' : 'fail',
    SITE_URL,
    $urlMatches ? '' : 'با آدرس واقعی نمی‌خواند؛ در config.php این مقدار را بگذارید: ' . $detectedUrl);

check('آدرس واقعی تشخیص‌داده‌شده', 'ok', $detectedUrl);

// ═══════════════════════════════════════════════════════════
//  ۵. مسیریابی و نشانی‌های تمیز
// ═══════════════════════════════════════════════════════════
$rewriteLoaded = null;

if (function_exists('apache_get_modules')) {
    $rewriteLoaded = in_array('mod_rewrite', apache_get_modules(), true);
}

// تابع apache_get_modules() فقط وقتی وجود دارد که PHP به‌صورت ماژول آپاچی
// اجرا شود. روی CGI/FPM — که امروز رایج‌تر است — این تابع نیست و نمی‌توان
// از خود آپاچی پرسید. این «ندانستن» ایراد نیست، چون آزمون زندهٔ بالا
// همان سؤال را به شکل قطعی جواب می‌دهد.
check('mod_rewrite',
    $rewriteLoaded === true ? 'ok' : ($rewriteLoaded === false ? 'fail' : 'info'),
    $rewriteLoaded === true ? 'فعال است'
        : ($rewriteLoaded === false ? 'فعال نیست'
        : 'از اینجا قابل تشخیص نیست — PHP به‌صورت ' . PHP_SAPI . ' اجرا می‌شود'),
    $rewriteLoaded === false
        ? 'نشانی‌های تمیز کار نمی‌کنند — گزینه «نشانی‌های تمیز» را خاموش کنید'
        : ($rewriteLoaded === null
            ? 'اشکالی ندارد؛ نتیجه «آزمون زنده مسیریابی» در بالای همین صفحه ملاک است'
            : ''));

$htaccessExists = is_file(__DIR__ . '/.htaccess');
check('فایل .htaccess', $htaccessExists ? 'ok' : 'warn',
    $htaccessExists ? 'موجود است' : 'یافت نشد',
    $htaccessExists ? '' : 'اگر روی آپاچی هستید، این فایل برای نشانی تمیز لازم است');

$pretty = (bool) getOption('pretty_urls', true);
check('نشانی‌های تمیز (تنظیمات)', 'ok', $pretty ? 'روشن' : 'خاموش');

// ═══════════════════════════════════════════════════════════
//  ۶. محتوا — آیا برگه‌ها واقعاً وجود دارند؟
// ═══════════════════════════════════════════════════════════
if ($state === 'installed') {
    try {
        $db = Database::getConnection();

        $counts = $db->query(
            'SELECT type, status, COUNT(*) AS total FROM ' . tbl('posts') . ' GROUP BY type, status'
        )->fetchAll();

        $summary = [];
        foreach ($counts as $row) {
            $summary[] = "{$row['type']}/{$row['status']}: {$row['total']}";
        }

        check('محتوای موجود', empty($summary) ? 'warn' : 'ok',
            empty($summary) ? 'هیچ محتوایی نیست' : implode('  ·  ', $summary),
            empty($summary) ? 'داده نمونه ساخته نشده؛ از پیشخوان محتوا اضافه کنید' : '');

        $pages = $db->query(
            'SELECT slug, status FROM ' . tbl('posts') . " WHERE type = 'page' ORDER BY id"
        )->fetchAll();

        foreach ($pages as $page) {
            $published = $page['status'] === 'publish';

            check("برگه «{$page['slug']}»", $published ? 'ok' : 'warn',
                $published ? 'منتشرشده' : $page['status'],
                $published ? '' : 'برگه منتشر نشده، پس در سایت دیده نمی‌شود');
        }

        if (empty($pages)) {
            check('برگه‌ها', 'warn', 'هیچ برگه‌ای در دیتابیس نیست',
                'آدرس برگه تا وقتی برگه ساخته نشود ۴۰۴ می‌دهد');
        }

        $theme = (string) getOption('active_theme', 'default');
        $themeOk = is_dir(THEMES_PATH . '/' . basename($theme));

        check('قالب فعال', $themeOk ? 'ok' : 'fail', $theme,
            $themeOk ? '' : 'پوشه قالب یافت نشد؛ به default برگردانده می‌شود');
    } catch (Throwable $e) {
        check('خواندن محتوا', 'fail', get_class($e), $e->getMessage());
    }
}

renderReport();

// ═══════════════════════════════════════════════════════════
//  نمایش گزارش
// ═══════════════════════════════════════════════════════════
function renderReport(): void
{
    global $rows, $problems, $detectedUrl, $pretty;

    $base = $detectedUrl ?? '';
    $okCount = count(array_filter($rows, fn($r) => $r['state'] === 'ok'));
    $failCount = count(array_filter($rows, fn($r) => $r['state'] === 'fail'));
    $warnCount = count(array_filter($rows, fn($r) => $r['state'] === 'warn'));

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>تشخیص مشکل — فرد سی‌ام‌اس</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:Vazirmatn,Tahoma,sans-serif;background:#0b0b12;color:#f2f2f7;
       padding:32px 20px;line-height:1.9;font-size:14px}
  .wrap{max-width:860px;margin:0 auto}
  h1{font-size:23px;margin-bottom:6px}
  h2{font-size:16px;margin:30px 0 12px;color:rgba(255,255,255,.8)}
  .muted{color:rgba(255,255,255,.55);font-size:13px}
  .tally{display:flex;gap:9px;flex-wrap:wrap;margin:18px 0 26px}
  .pill{padding:6px 15px;border-radius:20px;font-size:13px;font-weight:600}
  .pill.ok{background:rgba(74,222,128,.14);color:#4ade80}
  .pill.warn{background:rgba(251,191,36,.14);color:#fbbf24}
  .pill.fail{background:rgba(248,113,113,.14);color:#f87171}
  table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;
        border:1px solid rgba(255,255,255,.09)}
  td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.07);vertical-align:top}
  tr:last-child td{border-bottom:none}
  .st{width:34px;text-align:center;font-weight:700}
  .st.ok{color:#4ade80}.st.warn{color:#fbbf24}.st.fail{color:#f87171}
  .st.info{color:rgba(255,255,255,.45)}
  tr.info .hint{color:rgba(255,255,255,.5)}
  .lbl{width:38%;color:rgba(255,255,255,.85)}
  .val{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;
       unicode-bidi:plaintext;direction:ltr;text-align:left}
  .hint{display:block;margin-top:5px;font-size:12px;color:#fbbf24;
        font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;text-align:right}
  .box{margin:24px 0;padding:17px 19px;border-radius:13px;
       border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.035)}
  .box.bad{border-color:rgba(248,113,113,.35);background:rgba(248,113,113,.07)}
  .box.good{border-color:rgba(74,222,128,.35);background:rgba(74,222,128,.07)}
  .box h3{font-size:15px;margin-bottom:9px}
  ol,ul{margin:9px 20px 0 0}
  li{margin-bottom:7px}
  code{background:rgba(255,255,255,.09);padding:2px 7px;border-radius:5px;
       font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;
       unicode-bidi:plaintext}
  #probe td:first-child{width:34px}
  .warnbox{margin-top:30px;padding:15px 18px;border-radius:12px;
       background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fcd34d}
</style>
</head>
<body>
<div class="wrap">
  <h1>تشخیص مشکل فرد سی‌ام‌اس</h1>
  <p class="muted">این صفحه وضعیت نصب شما را بررسی می‌کند.</p>

  <div class="tally">
    <span class="pill ok">درست: <?= $okCount ?></span>
    <?php if ($warnCount): ?><span class="pill warn">هشدار: <?= $warnCount ?></span><?php endif; ?>
    <?php if ($failCount): ?><span class="pill fail">مشکل: <?= $failCount ?></span><?php endif; ?>
  </div>

  <?php if (!empty($problems)): ?>
    <div class="box bad">
      <h3>مشکل‌هایی که باید رفع شوند</h3>
      <ol><?php foreach ($problems as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ol>
    </div>
  <?php else: ?>
    <div class="box good">
      <h3>هیچ مشکل قطعی پیدا نشد</h3>
      <p class="muted">اگر برگه‌ها باز نمی‌شوند، نتیجه آزمون مسیریابی پایین را ببینید.</p>
    </div>
  <?php endif; ?>

  <h2>آزمون زنده مسیریابی</h2>
  <p class="muted">
    این آزمون از داخل مرورگر شما اجرا می‌شود و نشان می‌دهد کدام نوع نشانی
    روی این هاست کار می‌کند.
  </p>
  <table id="probe">
    <tr><td class="st" data-cell="pretty-state">…</td>
        <td class="lbl">نشانی تمیز</td>
        <td class="val" data-cell="pretty-value">در حال بررسی…</td></tr>
    <tr><td class="st" data-cell="query-state">…</td>
        <td class="lbl">نشانی پرسمانی</td>
        <td class="val" data-cell="query-value">در حال بررسی…</td></tr>
  </table>
  <div id="verdict"></div>

  <h2>جزئیات بررسی</h2>
  <table>
    <?php foreach ($rows as $r): ?>
      <tr class="<?= $r['state'] ?>">
        <td class="st <?= $r['state'] ?>"><?= match ($r['state']) {
            'ok'   => '✓',
            'warn' => '!',
            'info' => 'i',
            default => '✕',
        } ?></td>
        <td class="lbl"><?= h($r['label']) ?></td>
        <td class="val">
          <?= h($r['value']) ?>
          <?php if ($r['hint'] !== ''): ?><span class="hint"><?= h($r['hint']) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>

  <div class="warnbox">
    پس از رفع مشکل، فایل <code>diagnose.php</code> را از سرور حذف کنید.
  </div>
</div>

<script>
  const BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const MARKER = 'FARDCMS_ROUTING_OK';

  function set(name, state, value) {
    const st = document.querySelector(`[data-cell="${name}-state"]`);
    const val = document.querySelector(`[data-cell="${name}-value"]`);
    st.textContent = state === 'ok' ? '✓' : '✕';
    st.className = 'st ' + state;
    val.textContent = value;
  }

  /** یک آدرس را می‌گیرد و می‌گوید آیا به کنترلر سایت رسیده است یا نه */
  async function probe(url) {
    try {
      const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
      const text = (await res.text()).trim();

      return { ok: res.ok && text === MARKER, status: res.status };
    } catch (e) {
      return { ok: false, status: 0 };
    }
  }

  (async () => {
    const pretty = await probe(`${BASE}/__fardcms_probe`);
    set('pretty', pretty.ok ? 'ok' : 'fail',
        pretty.ok ? 'کار می‌کند' : `کار نمی‌کند (کد ${pretty.status})`);

    const query = await probe(`${BASE}/index.php?route=__fardcms_probe`);
    set('query', query.ok ? 'ok' : 'fail',
        query.ok ? 'کار می‌کند' : `کار نمی‌کند (کد ${query.status})`);

    const box = document.getElementById('verdict');

    if (pretty.ok) {
      box.className = 'box good';
      box.innerHTML = '<h3>نشانی تمیز روی این هاست کار می‌کند</h3>'
        + '<p class="muted">اگر برگه‌ای باز نمی‌شود، علت از تنظیمات نشانی نیست؛ '
        + 'بخش «برگه‌ها» در جدول پایین را ببینید — احتمالاً آن برگه منتشر نشده است.</p>';
    } else if (query.ok) {
      box.className = 'box bad';
      box.innerHTML = '<h3>علت پیدا شد: mod_rewrite روی این هاست کار نمی‌کند</h3>'
        + '<p>نشانی تمیز مثل <code>/contact</code> به سایت نمی‌رسد، اما '
        + '<code>index.php?route=contact</code> درست کار می‌کند. دو راه دارید:</p>'
        + '<ul>'
        + '<li><strong>راه سریع:</strong> در پیشخوان به <strong>تنظیمات → پیشرفته</strong> بروید '
        + 'و گزینه <strong>«نشانی‌های تمیز»</strong> را خاموش کنید. همه پیوندها بلافاصله درست می‌شوند.</li>'
        + '<li><strong>راه بهتر:</strong> از میزبان هاست بخواهید <code>mod_rewrite</code> و '
        + '<code>AllowOverride All</code> را برای این دامنه فعال کند، سپس گزینه را روشن نگه دارید.</li>'
        + '</ul>';
    } else {
      box.className = 'box bad';
      box.innerHTML = '<h3>هیچ‌کدام از دو نوع نشانی پاسخ نداد</h3>'
        + '<p>یعنی مشکل پیش از مسیریابی است. جدول پایین را ببینید؛ '
        + 'به‌احتمال زیاد اتصال دیتابیس یا آدرس سایت (SITE_URL) اشتباه است.</p>';
    }
  })();
</script>
</body>
</html>
    <?php
}
