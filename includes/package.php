<?php
/**
 * نصب قالب و افزونه از فایل زیپ
 *
 * ┌───────────────────────────────────────────────────────────────┐
 * │  این کد کدِ اجراشدنی روی سرور می‌نشاند. فقط مدیر کل باید       │
 * │  دسترسی داشته باشد و بسته فقط از منبع مورد اعتماد باشد.       │
 * └───────────────────────────────────────────────────────────────┘
 *
 * خطرهایی که اینجا مهار شده‌اند:
 *
 *   zip-slip     نامی مثل ../../config.php یا /etc/passwd داخل زیپ
 *                می‌تواند فایل را بیرون از پوشه مقصد بنویسد
 *   پیوند نمادین ورودی‌های symlink داخل زیپ به فایل‌های بیرون اشاره
 *                می‌کنند؛ اصلاً استخراج نمی‌شوند
 *   زیپ‌بمب      نسبت فشرده‌سازی بالا حافظه و دیسک را پر می‌کند
 *   پسوند خطرناک .htaccess و .phar و امثالش استخراج نمی‌شوند
 *   بازنویسی     نصب روی پوشه موجود فقط با تأیید صریح انجام می‌شود
 */

/** بیشترین حجم بسته فشرده */
const PACKAGE_MAX_ZIP = 20 * 1024 * 1024;      // ۲۰ مگابایت

/** بیشترین حجم بازشده و بیشترین تعداد فایل */
const PACKAGE_MAX_UNPACKED = 80 * 1024 * 1024; // ۸۰ مگابایت
const PACKAGE_MAX_FILES = 2000;

/**
 * پسوندهایی که داخل بسته پذیرفته می‌شوند
 *
 * php لازم است (قالب و افزونه کد دارند) ولی بقیه‌ی فرمت‌های اجراشدنی
 * وب‌سرور نه.
 */
function packageAllowedExtensions(): array
{
    return [
        'php', 'json', 'css', 'js', 'mjs', 'html', 'txt', 'md',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'mp3', 'ogg', 'pot', 'po', 'mo', 'xml',
    ];
}

/**
 * نصب یک بسته زیپ به‌عنوان قالب یا افزونه
 *
 * @param array<string, mixed> $file  یک ورودی از $_FILES
 * @param 'theme'|'plugin'     $kind
 * @return array{success:bool, message:string, slug?:string}
 */
function installPackage(array $file, string $kind, bool $overwrite = false): array
{
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'message' =>
            'افزونه zip در PHP این هاست فعال نیست؛ بسته را دستی آپلود کنید'];
    }

    if (!in_array($kind, ['theme', 'plugin'], true)) {
        return ['success' => false, 'message' => 'نوع بسته نامعتبر است'];
    }

    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => uploadErrorMessage((int) $error)];
    }

    if (($file['size'] ?? 0) > PACKAGE_MAX_ZIP) {
        return ['success' => false, 'message' =>
            'حجم بسته بیش از ' . formatBytes(PACKAGE_MAX_ZIP) . ' است'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');

    if (!is_uploaded_file($tmp)) {
        return ['success' => false, 'message' => 'فایل آپلودشده معتبر نیست'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);

    if (!in_array($mime, ['application/zip', 'application/x-zip-compressed'], true)) {
        return ['success' => false, 'message' => 'فقط فایل زیپ پذیرفته می‌شود'];
    }

    $zip = new ZipArchive();

    if ($zip->open($tmp) !== true) {
        return ['success' => false, 'message' => 'فایل زیپ خوانده نشد یا خراب است'];
    }

    $inspection = inspectPackageZip($zip);

    if (!$inspection['ok']) {
        $zip->close();

        return ['success' => false, 'message' => $inspection['message']];
    }

    $slug = $inspection['slug'];
    $root = pathinfo($slug, PATHINFO_BASENAME);
    $target = ($kind === 'theme' ? THEMES_PATH : pluginsPath()) . '/' . $root;

    if ($root === 'default') {
        $zip->close();

        return ['success' => false, 'message' => 'نام «default» رزرو شده است'];
    }

    if (is_dir($target) && !$overwrite) {
        $zip->close();

        return ['success' => false, 'message' =>
            'قبلاً نصب شده است؛ برای جایگزینی گزینه بازنویسی را بزنید', 'slug' => $root];
    }

    // ابتدا در پوشه موقت باز می‌شود: اگر وسط کار خطایی رخ دهد، نصب
    // قبلی دست‌نخورده می‌ماند
    $staging = STORAGE_PATH . '/package-' . bin2hex(random_bytes(6));

    if (!@mkdir($staging, 0755, true)) {
        $zip->close();

        return ['success' => false, 'message' => 'پوشه موقت ساخته نشد؛ دسترسی storage/ را بررسی کنید'];
    }

    $extracted = extractPackage($zip, $staging, $inspection['prefix']);
    $zip->close();

    if (!$extracted['ok']) {
        removeDirectory($staging);

        return ['success' => false, 'message' => $extracted['message']];
    }

    // شناسنامه باید واقعاً همان چیزی باشد که ادعا شده
    $valid = $kind === 'theme'
        ? is_file("$staging/index.php")
        : (is_file("$staging/plugin.json") || is_file("$staging/$root.php"));

    if (!$valid) {
        removeDirectory($staging);

        return ['success' => false, 'message' => $kind === 'theme'
            ? 'این بسته قالب نیست: فایل index.php ندارد'
            : 'این بسته افزونه نیست: plugin.json یا فایل اصلی ندارد'];
    }

    if (is_dir($target)) {
        // افزونه‌ای که جایگزین می‌شود نباید در حالت فعال نیمه‌کاره بماند
        if ($kind === 'plugin' && in_array($root, activePlugins(), true)) {
            deactivatePlugin($root);
        }

        removeDirectory($target);
    }

    if (!@rename($staging, $target)) {
        removeDirectory($staging);

        return ['success' => false, 'message' => 'انتقال فایل‌ها به مقصد ممکن نشد'];
    }

    logActivity("install_$kind", $kind, 0, "نصب بسته: $root");

    return ['success' => true, 'slug' => $root, 'message' =>
        ($kind === 'theme' ? 'قالب' : 'افزونه') . ' با موفقیت نصب شد'];
}

/**
 * بررسی محتوای زیپ پیش از استخراج
 *
 * @return array{ok:bool, message:string, slug:string, prefix:string}
 */
function inspectPackageZip(ZipArchive $zip): array
{
    $fail = fn(string $message) => ['ok' => false, 'message' => $message, 'slug' => '', 'prefix' => ''];

    if ($zip->numFiles === 0) {
        return $fail('بسته خالی است');
    }

    if ($zip->numFiles > PACKAGE_MAX_FILES) {
        return $fail('تعداد فایل‌های بسته بیش از حد مجاز است');
    }

    $total = 0;
    $roots = [];
    $names = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);

        if ($stat === false) {
            return $fail('خواندن فهرست بسته ممکن نشد');
        }

        $name = str_replace('\\', '/', (string) $stat['name']);

        // مسیر مطلق، بازگشت به بالا، یا نام مشکوک
        if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)
            || str_contains($name, "\0") || preg_match('/^[A-Za-z]:/', $name)) {
            return $fail("مسیر نامعتبر در بسته: $name");
        }

        $total += (int) $stat['size'];

        if ($total > PACKAGE_MAX_UNPACKED) {
            return $fail('حجم بازشده بسته بیش از ' . formatBytes(PACKAGE_MAX_UNPACKED) . ' است');
        }

        $isDir = str_ends_with($name, '/');

        if (!$isDir) {
            $base = basename($name);
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            // فایل‌های پنهان و تنظیمات سرور استخراج نمی‌شوند
            if (str_starts_with($base, '.')) {
                continue;
            }

            if (!in_array($ext, packageAllowedExtensions(), true)) {
                return $fail("پسوند غیرمجاز در بسته: .$ext");
            }

            $names[] = $name;
        }

        $roots[explode('/', $name)[0]] = true;
    }

    if (empty($names)) {
        return $fail('بسته هیچ فایل قابل نصبی ندارد');
    }

    // بسته باید یک پوشه ریشه داشته باشد؛ اگر فایل‌ها در ریشه زیپ باشند،
    // نام پوشه از نام خود فایل زیپ گرفته نمی‌شود چون قابل اعتماد نیست.
    $rootNames = array_keys($roots);

    if (count($rootNames) !== 1) {
        return $fail('بسته باید یک پوشه اصلی داشته باشد که همه فایل‌ها داخل آن باشند');
    }

    $slug = $rootNames[0];

    if (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
        return $fail("نام پوشه بسته فقط می‌تواند حروف انگلیسی، عدد، خط تیره و زیرخط باشد: $slug");
    }

    return ['ok' => true, 'message' => '', 'slug' => $slug, 'prefix' => "$slug/"];
}

/**
 * استخراج امن، فایل به فایل
 *
 * از extractTo استفاده نمی‌شود چون همه چیز را همان‌طور که هست بیرون
 * می‌ریزد؛ اینجا هر مسیر دوباره سنجیده و فایل‌های ناخواسته رد می‌شوند.
 *
 * @return array{ok:bool, message:string}
 */
function extractPackage(ZipArchive $zip, string $staging, string $prefix): array
{
    $realStaging = realpath($staging);

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);

        if ($stat === false) {
            continue;
        }

        $name = str_replace('\\', '/', (string) $stat['name']);

        if (!str_starts_with($name, $prefix)) {
            continue;
        }

        $relative = substr($name, strlen($prefix));

        if ($relative === '') {
            continue;
        }

        $base = basename($relative);

        // فایل‌های پنهان (از جمله .htaccess) و پوشه‌های ابزار توسعه
        if (str_starts_with($base, '.') || preg_match('#(^|/)\.#', $relative)) {
            continue;
        }

        $destination = "$staging/$relative";

        if (str_ends_with($name, '/')) {
            if (!is_dir($destination) && !@mkdir($destination, 0755, true)) {
                return ['ok' => false, 'message' => "ساخت پوشه ممکن نشد: $relative"];
            }

            continue;
        }

        $parent = dirname($destination);

        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            return ['ok' => false, 'message' => "ساخت پوشه ممکن نشد: $relative"];
        }

        // بررسی دوم پس از حل مسیر: پیوند نمادین یا مسیر عجیب نباید از
        // پوشه موقت بیرون بزند
        $realParent = realpath($parent);

        if ($realParent === false || !str_starts_with($realParent, (string) $realStaging)) {
            return ['ok' => false, 'message' => "مسیر بسته از پوشه مقصد بیرون می‌زند: $relative"];
        }

        $stream = $zip->getStream($name);

        if ($stream === false) {
            return ['ok' => false, 'message' => "خواندن فایل از بسته ممکن نشد: $relative"];
        }

        $out = @fopen($destination, 'wb');

        if ($out === false) {
            fclose($stream);

            return ['ok' => false, 'message' => "نوشتن فایل ممکن نشد: $relative"];
        }

        stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);
        @chmod($destination, 0644);
    }

    return ['ok' => true, 'message' => ''];
}

/**
 * حذف بازگشتی یک پوشه
 *
 * فقط داخل themes/ ، plugins/ و storage/ اجازه دارد کار کند تا یک اشتباه
 * برنامه‌نویسی نتواند جای دیگری را پاک کند.
 */
function removeDirectory(string $path): bool
{
    $real = realpath($path);

    if ($real === false || !is_dir($real)) {
        return false;
    }

    $allowed = array_filter([
        realpath(THEMES_PATH),
        realpath(pluginsPath()),
        realpath(STORAGE_PATH),
    ]);

    $inside = false;

    foreach ($allowed as $root) {
        if ($real !== $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            $inside = true;
            break;
        }
    }

    if (!$inside) {
        error_log("removeDirectory refused outside allowed roots: $real");

        return false;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        // پیوند نمادین دنبال نمی‌شود؛ فقط خودش پاک می‌شود
        if ($item->isLink() || $item->isFile()) {
            @unlink($item->getPathname());
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        }
    }

    return @rmdir($real);
}

/**
 * حذف کامل یک قالب یا افزونه
 *
 * @return array{success:bool, message:string}
 */
function removePackage(string $slug, string $kind): array
{
    if (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
        return ['success' => false, 'message' => 'نام نامعتبر است'];
    }

    if ($kind === 'theme') {
        if ($slug === 'default') {
            return ['success' => false, 'message' => 'قالب پیش‌فرض حذف نمی‌شود'];
        }

        if ($slug === getOption('active_theme', 'default')) {
            return ['success' => false, 'message' =>
                'این قالب فعال است؛ اول قالب دیگری را فعال کنید'];
        }

        $path = THEMES_PATH . '/' . $slug;
    } else {
        if (in_array($slug, activePlugins(), true)) {
            return ['success' => false, 'message' =>
                'این افزونه فعال است؛ اول غیرفعالش کنید'];
        }

        $path = pluginsPath() . '/' . $slug;
    }

    if (!is_dir($path)) {
        return ['success' => false, 'message' => 'یافت نشد'];
    }

    if (!removeDirectory($path)) {
        return ['success' => false, 'message' => 'حذف فایل‌ها ممکن نشد؛ دسترسی پوشه را بررسی کنید'];
    }

    logActivity("delete_$kind", $kind, 0, "حذف بسته: $slug");

    return ['success' => true, 'message' => 'با موفقیت حذف شد'];
}
