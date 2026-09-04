<?php
/**
 * نصب‌کننده فرد سی‌ام‌اس
 *
 * وب:  http://localhost:8765/setup.php
 * خط فرمان:  php setup.php --email=admin@site.com --password='Secret@123' [--force]
 *
 * پس از نصب موفق، فایل storage/installed.lock ساخته می‌شود و اجرای دوباره
 * نصب‌کننده مسدود خواهد شد.
 */

require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/schema.php';

const LOCK_FILE = STORAGE_PATH . '/installed.lock';

$isCli = PHP_SAPI === 'cli';

// ─── تجزیه آرگومان‌های خط فرمان ────────────────────────────
$cliArgs = [];
if ($isCli) {
    foreach (array_slice($argv, 1) as $arg) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
            $cliArgs[$m[1]] = $m[2] ?? '1';
        }
    }
}

$force = !empty($cliArgs['force']) || (!$isCli && isset($_GET['force']));

// ─── بررسی نصب قبلی ────────────────────────────────────────
if (file_exists(LOCK_FILE) && !$force) {
    $message = 'فرد سی‌ام‌اس قبلاً نصب شده است. برای نصب مجدد، فایل storage/installed.lock را حذف کنید.';

    if ($isCli) {
        fwrite(STDERR, "⚠ $message\n");
        exit(1);
    }

    renderPage('نصب انجام شده', '<div class="alert alert-warn">' . e($message) . '</div>'
        . '<a class="btn" href="' . e(siteUrl('admin/')) . '">ورود به پنل مدیریت</a>');
    exit;
}

// ─── بررسی پیش‌نیازها ──────────────────────────────────────
/**
 * @return array<int, array{label: string, ok: bool, hint: string}>
 */
function checkRequirements(): array
{
    $checks = [];

    $checks[] = [
        'label' => 'نسخه PHP (حداقل ۸.۱)',
        'ok'    => PHP_VERSION_ID >= 80100,
        'hint'  => 'نسخه فعلی: ' . PHP_VERSION,
    ];

    foreach (['pdo_mysql' => 'اتصال به MySQL', 'mbstring' => 'پردازش متن فارسی',
              'json' => 'پردازش JSON', 'fileinfo' => 'تشخیص نوع فایل'] as $ext => $why) {
        $checks[] = [
            'label' => "افزونه $ext",
            'ok'    => extension_loaded($ext),
            'hint'  => $why,
        ];
    }

    $checks[] = [
        'label' => 'افزونه gd (ساخت بندانگشتی)',
        'ok'    => extension_loaded('gd'),
        'hint'  => 'اختیاری — بدون آن تصاویر بندانگشتی ساخته نمی‌شوند',
    ];

    foreach ([UPLOADS_PATH => 'پوشه uploads', STORAGE_PATH => 'پوشه storage'] as $path => $label) {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }

        $checks[] = [
            'label' => "قابلیت نوشتن در $label",
            'ok'    => is_dir($path) && is_writable($path),
            'hint'  => $path,
        ];
    }

    return $checks;
}

$requirements = checkRequirements();

// افزونه gd اختیاری است و مانع نصب نمی‌شود
$blocking = array_filter($requirements, fn($c) => !$c['ok'] && !str_contains($c['label'], 'gd'));

// ─── اجرای نصب ─────────────────────────────────────────────
$shouldInstall = $isCli || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

if ($shouldInstall && empty($blocking)) {
    $input = $isCli ? $cliArgs : $_POST;

    $siteTitle = trim((string) ($input['site_title'] ?? SITE_NAME));
    $adminName = trim((string) ($input['name'] ?? 'مدیر سایت'));
    $adminEmail = trim((string) ($input['email'] ?? 'admin@example.com'));
    $adminUsername = trim((string) ($input['username'] ?? 'admin'));
    $adminPassword = (string) ($input['password'] ?? '');

    // در حالت خط فرمان، اگر رمزی داده نشود یکی ساخته می‌شود
    $generatedPassword = false;
    if ($adminPassword === '') {
        if ($isCli) {
            $adminPassword = generateReadablePassword();
            $generatedPassword = true;
        } else {
            renderInstallForm($requirements, $blocking, 'وارد کردن رمز عبور الزامی است', $_POST);
            exit;
        }
    }

    $result = runInstall($siteTitle, $adminName, $adminEmail, $adminUsername, $adminPassword);

    if (!$result['success']) {
        if ($isCli) {
            fwrite(STDERR, '✗ ' . $result['message'] . "\n");
            exit(1);
        }

        renderInstallForm($requirements, $blocking, $result['message'], $_POST);
        exit;
    }

    // ─── گزارش موفقیت ──────────────────────────────────────
    if ($isCli) {
        echo "\n";
        echo "✓ نصب فرد سی‌ام‌اس با موفقیت انجام شد\n\n";
        echo "  جداول ساخته‌شده: " . count($result['tables']) . "\n";
        echo "  آدرس سایت:       " . siteUrl('') . "\n";
        echo "  پنل مدیریت:      " . siteUrl('admin/') . "\n";
        echo "  ایمیل:           $adminEmail\n";
        echo "  نام کاربری:      $adminUsername\n";
        echo "  رمز عبور:        " . ($generatedPassword ? $adminPassword : '(همان مقدار وارد شده)') . "\n\n";

        if ($generatedPassword) {
            echo "⚠ رمز عبور بالا را ذخیره کنید؛ دیگر نمایش داده نمی‌شود.\n\n";
        }

        exit(0);
    }

    renderPage('نصب کامل شد', '
        <div class="success-badge">✓</div>
        <h2 class="center">نصب با موفقیت انجام شد</h2>
        <p class="muted center">' . e(count($result['tables'])) . ' جدول ساخته شد و محتوای نمونه اضافه شد.</p>
        <div class="summary">
            <div class="row"><span>پنل مدیریت</span><code>' . e(siteUrl('admin/')) . '</code></div>
            <div class="row"><span>نام کاربری</span><code>' . e($adminUsername) . '</code></div>
            <div class="row"><span>ایمیل</span><code>' . e($adminEmail) . '</code></div>
        </div>
        <div class="alert alert-warn">
            <strong>گام بعدی:</strong> برای امنیت سایت، فایل <code>setup.php</code> را حذف کنید.
        </div>
        <a class="btn" href="' . e(siteUrl('admin/')) . '">ورود به پنل مدیریت</a>
        <a class="btn btn-ghost" href="' . e(siteUrl('')) . '">مشاهده سایت</a>
    ');
    exit;
}

// ─── نمایش فرم نصب ─────────────────────────────────────────
if ($isCli) {
    fwrite(STDERR, "✗ پیش‌نیازهای نصب برآورده نشده است:\n");

    foreach ($blocking as $check) {
        fwrite(STDERR, '  - ' . $check['label'] . ' (' . $check['hint'] . ")\n");
    }

    exit(1);
}

renderInstallForm($requirements, $blocking);

// ═══════════════════════════════════════════════════════════
//  توابع نصب
// ═══════════════════════════════════════════════════════════

/**
 * اجرای کامل مراحل نصب
 *
 * @return array{success: bool, message: string, tables?: string[]}
 */
function runInstall(
    string $siteTitle,
    string $adminName,
    string $adminEmail,
    string $adminUsername,
    string $adminPassword
): array {
    // اعتبارسنجی ورودی‌ها
    if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'فرمت ایمیل مدیر نامعتبر است'];
    }

    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $adminUsername)) {
        return ['success' => false, 'message' => 'نام کاربری باید ۳ تا ۵۰ کاراکتر لاتین باشد'];
    }

    if (mb_strlen($adminName, 'UTF-8') < 2) {
        return ['success' => false, 'message' => 'نام مدیر باید حداقل ۲ کاراکتر باشد'];
    }

    $passwordErrors = validatePasswordStrength($adminPassword);
    if (!empty($passwordErrors)) {
        return ['success' => false, 'message' => implode(' | ', $passwordErrors)];
    }

    // ─── ساخت دیتابیس ──────────────────────────────────────
    try {
        $base = Database::getBaseConnection();
        $base->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '`
                     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        error_log('Install failed (create database): ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'ساخت دیتابیس ممکن نشد. تنظیمات اتصال در config.php را بررسی کنید.',
        ];
    }

    // ─── ساخت جداول و داده‌های اولیه ───────────────────────
    try {
        $db = Database::getConnection();
        $tables = createSchema($db);

        // ساخت یا به‌روزرسانی حساب مدیر
        $stmt = $db->prepare('SELECT id FROM ' . tbl('users') . ' WHERE email = ? OR username = ? LIMIT 1');
        $stmt->execute([$adminEmail, $adminUsername]);
        $existing = $stmt->fetch();

        $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        if ($existing) {
            $adminId = (int) $existing['id'];
            $db->prepare('UPDATE ' . tbl('users') . " SET name = ?, password = ?,
                          role = 'administrator', is_active = 1 WHERE id = ?")
               ->execute([$adminName, $hash, $adminId]);
        } else {
            $db->prepare('INSERT INTO ' . tbl('users') . " (name, username, email, password, role, is_active)
                          VALUES (?, ?, ?, ?, 'administrator', 1)")
               ->execute([$adminName, $adminUsername, $adminEmail, $hash]);

            $adminId = (int) $db->lastInsertId();
        }

        seedInitialData($db, $adminId);

        if ($siteTitle !== '') {
            setOption('site_title', $siteTitle);
        }
    } catch (Throwable $e) {
        error_log('Install failed (schema/seed): ' . $e->getMessage());

        return [
            'success' => false,
            'message' => 'ساخت جداول با خطا مواجه شد' . (DEBUG_MODE ? ': ' . $e->getMessage() : ''),
        ];
    }

    // ─── آماده‌سازی پوشه‌ها ────────────────────────────────
    prepareDirectories();

    // ─── ثبت قفل نصب ───────────────────────────────────────
    @file_put_contents(LOCK_FILE, json_encode([
        'installed_at' => date('c'),
        'version'      => '1.0.0',
        'db_name'      => DB_NAME,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return ['success' => true, 'message' => 'نصب انجام شد', 'tables' => $tables];
}

/**
 * ساخت پوشه‌های لازم و محافظت از آن‌ها
 */
function prepareDirectories(): void
{
    foreach ([UPLOADS_PATH, STORAGE_PATH, STORAGE_PATH . '/cache'] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // اجرای PHP در پوشه آپلود مسدود می‌شود
    @file_put_contents(UPLOADS_PATH . '/.htaccess',
        "# اجرای اسکریپت در پوشه آپلود مجاز نیست\n"
        . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phps|cgi|pl|py|sh)$\">\n"
        . "    Require all denied\n"
        . "</FilesMatch>\n"
        . "php_flag engine off\n"
    );

    // پوشه storage نباید از بیرون قابل دسترسی باشد
    @file_put_contents(STORAGE_PATH . '/.htaccess', "Require all denied\n");
}

/**
 * ساخت رمز عبور تصادفی خوانا و معتبر
 */
function generateReadablePassword(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnopqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%&*';

    $password = $upper[random_int(0, strlen($upper) - 1)]
              . $lower[random_int(0, strlen($lower) - 1)]
              . $digits[random_int(0, strlen($digits) - 1)]
              . $symbols[random_int(0, strlen($symbols) - 1)];

    $all = $upper . $lower . $digits;
    for ($i = 0; $i < 10; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }

    return str_shuffle($password);
}

// ═══════════════════════════════════════════════════════════
//  نمایش صفحات
// ═══════════════════════════════════════════════════════════

/**
 * نمایش فرم نصب
 *
 * @param array<int, array{label: string, ok: bool, hint: string}> $requirements
 * @param array<int, array{label: string, ok: bool, hint: string}> $blocking
 * @param array<string, mixed> $old
 */
function renderInstallForm(array $requirements, array $blocking, string $error = '', array $old = []): void
{
    $rows = '';
    foreach ($requirements as $check) {
        $icon = $check['ok'] ? '✓' : '✕';
        $cls = $check['ok'] ? 'ok' : (str_contains($check['label'], 'gd') ? 'warn' : 'fail');
        $rows .= '<div class="req ' . $cls . '">'
               . '<span class="req-icon">' . $icon . '</span>'
               . '<span class="req-label">' . e($check['label']) . '</span>'
               . '<span class="req-hint">' . e($check['hint']) . '</span>'
               . '</div>';
    }

    $val = fn(string $key, string $default = '') => e((string) ($old[$key] ?? $default));

    $body = '<p class="muted center">پیش از شروع، تنظیمات اتصال دیتابیس را در <code>config.php</code> بررسی کنید.</p>'
        . '<h3 class="section-title">پیش‌نیازهای سرور</h3>'
        . '<div class="req-list">' . $rows . '</div>';

    if (!empty($blocking)) {
        $body .= '<div class="alert alert-error">برای ادامه، موارد قرمز بالا باید برطرف شوند.</div>';
    } else {
        if ($error !== '') {
            $body .= '<div class="alert alert-error">' . e($error) . '</div>';
        }

        $body .= '<h3 class="section-title">اطلاعات سایت و مدیر</h3>'
            . '<form method="post" autocomplete="off">'
            . '<label>عنوان سایت<input type="text" name="site_title" value="' . $val('site_title', SITE_NAME) . '" required></label>'
            . '<label>نام مدیر<input type="text" name="name" value="' . $val('name', 'مدیر سایت') . '" required></label>'
            . '<label>نام کاربری<input type="text" name="username" value="' . $val('username', 'admin') . '" pattern="[a-zA-Z0-9._-]{3,50}" required></label>'
            . '<label>ایمیل<input type="email" name="email" value="' . $val('email') . '" required></label>'
            . '<label>رمز عبور<input type="password" name="password" minlength="8" required'
            . ' placeholder="حداقل ۸ کاراکتر، شامل حروف بزرگ، کوچک و عدد"></label>'
            . '<button type="submit" class="btn btn-block">شروع نصب</button>'
            . '</form>';
    }

    renderPage('نصب فرد سی‌ام‌اس', $body);
}

/**
 * چیدمان کلی صفحات نصب‌کننده
 */
function renderPage(string $title, string $body): void
{
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . e($title) . '</title>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:Vazirmatn,Tahoma,sans-serif;background:#0a0a0f;color:#fff;min-height:100vh;
       display:flex;align-items:flex-start;justify-content:center;padding:40px 20px}
  body::before{content:"";position:fixed;inset:0;z-index:0;background:
       radial-gradient(ellipse at 20% 30%,rgba(120,80,255,.18),transparent 55%),
       radial-gradient(ellipse at 80% 70%,rgba(255,100,150,.12),transparent 55%)}
  .wrap{position:relative;z-index:1;width:100%;max-width:620px;background:rgba(255,255,255,.04);
        backdrop-filter:blur(40px);border:1px solid rgba(255,255,255,.09);border-radius:24px;padding:44px 40px;
        box-shadow:0 30px 60px -12px rgba(0,0,0,.55)}
  .logo{width:60px;height:60px;margin:0 auto 18px;border-radius:18px;display:flex;align-items:center;
        justify-content:center;font-size:26px;background:linear-gradient(135deg,#7850ff,#ff6496);
        box-shadow:0 10px 30px -6px rgba(120,80,255,.5)}
  h1{font-size:25px;text-align:center;margin-bottom:8px}
  h2{font-size:21px;margin-bottom:8px}
  .center{text-align:center}
  .muted{color:rgba(255,255,255,.55);font-size:14px;line-height:2;margin-bottom:8px}
  .section-title{font-size:15px;color:rgba(255,255,255,.75);margin:28px 0 12px;font-weight:600}
  .req-list{display:flex;flex-direction:column;gap:7px}
  .req{display:grid;grid-template-columns:24px 1fr auto;align-items:center;gap:10px;padding:11px 14px;
       border-radius:11px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);font-size:13.5px}
  .req-icon{font-weight:700;text-align:center}
  .req.ok .req-icon{color:#4ade80}.req.fail .req-icon{color:#f87171}.req.warn .req-icon{color:#fbbf24}
  .req.fail{border-color:rgba(248,113,113,.35);background:rgba(248,113,113,.07)}
  .req-hint{color:rgba(255,255,255,.4);font-size:12px;max-width:230px;text-align:left;
            direction:ltr;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  form{display:flex;flex-direction:column;gap:15px;margin-top:6px}
  label{display:flex;flex-direction:column;gap:7px;font-size:13.5px;color:rgba(255,255,255,.75)}
  input{padding:14px 16px;border-radius:13px;border:1px solid rgba(255,255,255,.1);
        background:rgba(255,255,255,.05);color:#fff;font-family:inherit;font-size:14.5px;outline:none;
        transition:border-color .25s,box-shadow .25s}
  input:focus{border-color:rgba(120,80,255,.55);box-shadow:0 0 0 4px rgba(120,80,255,.12)}
  input::placeholder{color:rgba(255,255,255,.28);font-size:13px}
  .btn{display:inline-block;padding:14px 34px;margin-top:10px;margin-left:8px;border:none;border-radius:13px;
       cursor:pointer;font-family:inherit;font-size:15px;font-weight:600;color:#fff;text-decoration:none;
       background:linear-gradient(135deg,#7850ff,#ff6496);transition:transform .2s,box-shadow .2s}
  .btn:hover{transform:translateY(-2px);box-shadow:0 12px 28px -8px rgba(120,80,255,.6)}
  .btn-block{width:100%;margin-left:0}
  .btn-ghost{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12)}
  .alert{padding:14px 16px;border-radius:12px;font-size:13.5px;line-height:1.9;margin:18px 0}
  .alert-error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:#fca5a5}
  .alert-warn{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);color:#fcd34d}
  .success-badge{width:70px;height:70px;margin:0 auto 20px;border-radius:50%;display:flex;align-items:center;
        justify-content:center;font-size:34px;background:rgba(74,222,128,.15);
        border:2px solid rgba(74,222,128,.45);color:#4ade80}
  .summary{margin:22px 0;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.08)}
  .summary .row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 16px;
        font-size:13.5px;background:rgba(255,255,255,.03)}
  .summary .row + .row{border-top:1px solid rgba(255,255,255,.07)}
  .summary span{color:rgba(255,255,255,.55)}
  code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px;direction:ltr;
       background:rgba(255,255,255,.07);padding:3px 8px;border-radius:6px}
  @media(max-width:520px){.wrap{padding:32px 22px}.req{grid-template-columns:22px 1fr}.req-hint{display:none}}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">✦</div>
  <h1>' . e($title) . '</h1>
  ' . $body . '
</div>
</body>
</html>';
}
