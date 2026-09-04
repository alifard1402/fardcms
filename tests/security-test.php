<?php
require dirname(__DIR__) . '/config.php';
/**
 * تست‌های امنیتی
 *
 * اجرا:  php tests/security-test.php
 */
$pass=0; $fail=0;
function chk(string $n, bool $ok, string $d=''): void {
  global $pass,$fail;
  $ok ? $pass++ : $fail++;
  printf("  %s  %s%s\n", $ok?'PASS':'FAIL', $n, (!$ok && $d) ? " — $d" : '');
}

echo "═══ پیمایش مسیر (path traversal) ═══\n";
// نام قالب از دیتابیس می‌آید؛ مقدار مخرب نباید از پوشه themes بیرون برود
setOption('active_theme', '../../etc');
chk('نام قالب مخرب مهار می‌شود', str_ends_with(activeThemePath(), '/themes/default'), activeThemePath());
setOption('active_theme', '/etc/passwd');
chk('مسیر مطلق در نام قالب مهار می‌شود', str_ends_with(activeThemePath(), '/themes/default'), activeThemePath());
setOption('active_theme', 'default');

// نام جدول باید فقط از الگوی مجاز بیاید
$tblSafe = false;
try { tbl('users; DROP TABLE x'); } catch (InvalidArgumentException) { $tblSafe = true; }
chk('نام جدول نامعتبر رد می‌شود', $tblSafe);

// نام ستون در uniqueSlug
$colSafe = false;
try { uniqueSlug('terms', 'x', null, ['taxonomy; DROP' => 'v']); } catch (InvalidArgumentException) { $colSafe = true; }
chk('نام ستون نامعتبر رد می‌شود', $colSafe);

echo "\n═══ آپلود فایل ═══\n";
$map = allowedMimeMap();
chk('پسوند اجرایی در فهرست مجاز نیست', !isset($map['php']) && !isset($map['phtml']) && !isset($map['sh']));
$allowed = array_map('trim', explode(',', UPLOAD_ALLOWED_TYPES));
chk('php در انواع مجاز نیست', !in_array('php', $allowed, true));
chk('htaccess در انواع مجاز نیست', !in_array('htaccess', $allowed, true));

// SVG باید از اسکریپت و رویداد پاک شود
$svg = sanitizeSvg('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script>'
  . '<a xlink:href="javascript:alert(1)"><rect onclick="alert(1)" width="10" height="10"/></a></svg>');
chk('SVG: اسکریپت حذف شد', $svg !== null && !str_contains($svg, '<script'));
chk('SVG: رویداد حذف شد', $svg !== null && !preg_match('/\son[a-z]+\s*=/i', $svg));
chk('SVG: پروتکل اجرایی حذف شد', $svg !== null && !str_contains($svg, 'javascript:'));
chk('SVG: غیر-SVG رد می‌شود', sanitizeSvg('<html><body>x</body></html>') === null);
chk('SVG: DOCTYPE/ENTITY حذف شد (XXE)',
    !str_contains((string) sanitizeSvg('<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>'), 'ENTITY'));

// پوشه آپلود باید از اجرای اسکریپت جلوگیری کند
$ht = @file_get_contents(UPLOADS_PATH . '/.htaccess');
chk('uploads/.htaccess اجرای php را مسدود می‌کند',
    $ht !== false && str_contains($ht, 'php') && (str_contains($ht, 'denied') || str_contains($ht, 'engine off')));

echo "\n═══ احراز هویت و رمز عبور ═══\n";
$hash = password_hash('Test@1234', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
chk('رمز با bcrypt هش می‌شود', str_starts_with($hash, '$2y$'));
chk('هزینه bcrypt کافی است', BCRYPT_COST >= 12);
chk('رمز ضعیف رد می‌شود', !empty(validatePasswordStrength('12345678')));
chk('رمز کوتاه رد می‌شود', !empty(validatePasswordStrength('Ab1')));
chk('رمز بدون عدد رد می‌شود', !empty(validatePasswordStrength('Abcdefghij')));
chk('رمز قوی پذیرفته می‌شود', empty(validatePasswordStrength('Str0ngPass!')));

// توکن «مرا به خاطر بسپار» باید هش‌شده ذخیره شود، نه خام
$src = file_get_contents(INCLUDES_PATH . '/auth.php');
chk('توکن remember هش‌شده ذخیره می‌شود', str_contains($src, "hash('sha256', \$rememberToken)"));
chk('توکن بازیابی هش‌شده ذخیره می‌شود',
    str_contains($src, "hash('sha256', \$token)"));
chk('session پس از ورود بازسازی می‌شود', str_contains($src, 'session_regenerate_id(true)'));
chk('مقایسه توکن CSRF با hash_equals است', str_contains($src, 'hash_equals'));

echo "\n═══ نقش‌ها و دسترسی‌ها ═══\n";
chk('مشارکت‌کننده اجازه انتشار ندارد', !in_array('publish_posts', roleCaps('contributor'), true));
chk('مشارکت‌کننده اجازه مدیریت کاربر ندارد', !in_array('manage_users', roleCaps('contributor'), true));
chk('نویسنده به محتوای دیگران دسترسی ندارد', !in_array('edit_others_posts', roleCaps('author'), true));
chk('نویسنده اجازه تنظیمات ندارد', !in_array('manage_settings', roleCaps('author'), true));
chk('ویراستار اجازه مدیریت کاربر ندارد', !in_array('manage_users', roleCaps('editor'), true));
chk('کاربر عادی فقط خواندن دارد', roleCaps('subscriber') === ['read']);
chk('نقش نامعتبر دسترسی ندارد', roleCaps('hacker') === []);
chk('مدیر کل همه دسترسی‌ها را دارد', in_array('manage_settings', roleCaps('administrator'), true));

echo "\n═══ محافظت از داده خروجی ═══\n";
// رکورد کاربر نباید هش رمز را بیرون بدهد
$u = getUser(1);
chk('getUser هش رمز را برنمی‌گرداند', $u !== null && !isset($u['password']));
$row = formatPostRow(['id'=>1,'status'=>'publish','password'=>'secret','updated_at'=>'2026-01-01','created_at'=>'2026-01-01']);
chk('formatPostRow کلمه رمز را حذف می‌کند', !isset($row['password']));
// شناسه نشست نباید افشا شود
$src = file_get_contents(INCLUDES_PATH . '/users.php');
chk('شناسه نشست در خروجی نیست', str_contains($src, "unset(\$row['id'])"));

echo "\n═══ تنظیمات ═══\n";
chk('عمر نشست محدود است', SESSION_LIFETIME > 0 && SESSION_LIFETIME <= 86400);
chk('محدودیت تلاش ورود فعال است', RATE_LIMIT_MAX > 0 && RATE_LIMIT_MAX <= 20);
chk('حجم آپلود محدود است', UPLOAD_MAX_SIZE > 0 && UPLOAD_MAX_SIZE <= 50*1024*1024);
chk('خطاها در حالت عادی نمایش داده نمی‌شوند', DEBUG_MODE || ini_get('display_errors') == '0');

echo "\n════ PASS: $pass  FAIL: $fail ════\n";
exit($fail>0?1:0);
