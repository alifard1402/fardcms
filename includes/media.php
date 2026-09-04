<?php
/**
 * کتابخانه رسانه — آپلود و مدیریت فایل‌ها
 */

/**
 * نگاشت پسوند مجاز به نوع MIME معتبر
 *
 * تکیه بر نوع اعلام‌شده توسط مرورگر امن نیست؛ نوع واقعی فایل با
 * finfo بررسی و با این فهرست مقایسه می‌شود.
 *
 * @return array<string, string[]>
 */
function allowedMimeMap(): array
{
    return [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'svg'  => ['image/svg+xml', 'text/plain', 'text/html', 'text/xml', 'application/xml'],
        'pdf'  => ['application/pdf'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'mp4'  => ['video/mp4'],
        'mp3'  => ['audio/mpeg', 'audio/mp3'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];
}

/**
 * آپلود یک فایل به کتابخانه رسانه
 *
 * @param array{name:string, type:string, tmp_name:string, error:int, size:int} $file رکورد $_FILES
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function uploadMedia(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => uploadErrorMessage((int) ($file['error'] ?? 0))];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'فایل آپلودشده نامعتبر است'];
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        return ['success' => false, 'message' => 'حجم فایل بیش از ' . formatBytes(UPLOAD_MAX_SIZE) . ' است'];
    }

    if ($file['size'] <= 0) {
        return ['success' => false, 'message' => 'فایل خالی است'];
    }

    $originalName = (string) $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $allowedExtensions = array_map('trim', explode(',', UPLOAD_ALLOWED_TYPES));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'message' => 'این نوع فایل مجاز نیست. مجاز: ' . UPLOAD_ALLOWED_TYPES];
    }

    // بررسی نوع واقعی فایل
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = (string) $finfo->file($file['tmp_name']);
    $expectedMimes = allowedMimeMap()[$extension] ?? [];

    if (!in_array($realMime, $expectedMimes, true)) {
        return ['success' => false, 'message' => 'محتوای فایل با پسوند آن هم‌خوانی ندارد'];
    }

    // فایل SVG می‌تواند شامل اسکریپت باشد و باید پاک‌سازی شود
    $svgContent = null;
    if ($extension === 'svg') {
        $svgContent = sanitizeSvg((string) file_get_contents($file['tmp_name']));

        if ($svgContent === null) {
            return ['success' => false, 'message' => 'فایل SVG معتبر نیست'];
        }
    }

    // مسیر ذخیره‌سازی بر اساس سال و ماه
    $subDir = date('Y/m');
    $targetDir = UPLOADS_PATH . '/' . $subDir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return ['success' => false, 'message' => 'ایجاد پوشه آپلود ممکن نشد'];
    }

    // نام فایل امن و یکتا
    $baseName = slugify(pathinfo($originalName, PATHINFO_FILENAME));
    $baseName = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $baseName) ?: 'file';
    $fileName = $baseName . '-' . substr(bin2hex(random_bytes(6)), 0, 8) . '.' . $extension;
    $relativePath = $subDir . '/' . $fileName;
    $targetPath = $targetDir . '/' . $fileName;

    // انتقال فایل
    if ($svgContent !== null) {
        if (file_put_contents($targetPath, $svgContent) === false) {
            return ['success' => false, 'message' => 'ذخیره فایل ممکن نشد'];
        }
    } elseif (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'message' => 'انتقال فایل ممکن نشد'];
    }

    chmod($targetPath, 0644);

    // ابعاد تصویر و ساخت بندانگشتی
    $width = null;
    $height = null;
    $thumbnail = null;

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        $info = @getimagesize($targetPath);

        if ($info !== false) {
            $width = (int) $info[0];
            $height = (int) $info[1];
            $thumbnail = createThumbnail($targetPath, $subDir, $fileName);
        }
    }

    // ثبت در دیتابیس
    $db = Database::getConnection();
    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('media') . '
         (user_id, file_name, file_path, thumbnail_path, original_name, mime_type, file_size, width, height, alt_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        currentUserId() ?: null,
        $fileName,
        $relativePath,
        $thumbnail,
        mb_substr($originalName, 0, 255, 'UTF-8'),
        $realMime,
        (int) $file['size'],
        $width,
        $height,
        '',
    ]);

    $id = (int) $db->lastInsertId();

    logActivity('upload_media', 'media', $id, 'آپلود فایل: ' . $originalName);

    return [
        'success' => true,
        'message' => 'فایل با موفقیت آپلود شد',
        'data'    => ['media' => getMediaItem($id)],
    ];
}

/**
 * دریافت فهرست رسانه‌ها با صفحه‌بندی
 *
 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
 */
function getMediaList(array $args = []): array
{
    $db = Database::getConnection();

    $page = max(1, (int) ($args['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($args['per_page'] ?? 24)));
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];

    if (!empty($args['search'])) {
        $conditions[] = likeCondition(
            ['m.original_name', 'm.alt_text'],
            trim((string) $args['search']),
            $params
        );
    }

    // فیلتر بر اساس نوع کلی فایل (تصویر، ویدیو، سند)
    if (!empty($args['type'])) {
        if ($args['type'] === 'image') {
            $conditions[] = "m.mime_type LIKE 'image/%'";
        } elseif ($args['type'] === 'video') {
            $conditions[] = "m.mime_type LIKE 'video/%'";
        } elseif ($args['type'] === 'audio') {
            $conditions[] = "m.mime_type LIKE 'audio/%'";
        } elseif ($args['type'] === 'document') {
            $conditions[] = "m.mime_type NOT LIKE 'image/%' AND m.mime_type NOT LIKE 'video/%' AND m.mime_type NOT LIKE 'audio/%'";
        }
    }

    // نویسنده‌ها فقط فایل‌های خودشان را می‌بینند
    if (!empty($args['user_id'])) {
        $conditions[] = 'm.user_id = :user_id';
        $params['user_id'] = (int) $args['user_id'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . tbl('media') . " m $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        'SELECT m.*, u.name AS user_name
         FROM ' . tbl('media') . ' m
         LEFT JOIN ' . tbl('users') . " u ON u.id = m.user_id
         $where
         ORDER BY m.id DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return [
        'items' => array_map('formatMediaRow', $stmt->fetchAll()),
        'meta'  => paginationMeta($total, $page, $perPage),
    ];
}

/**
 * دریافت یک رسانه
 */
function getMediaItem(int $id): ?array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT m.*, u.name AS user_name
         FROM ' . tbl('media') . ' m
         LEFT JOIN ' . tbl('users') . ' u ON u.id = m.user_id
         WHERE m.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? formatMediaRow($row) : null;
}

/**
 * به‌روزرسانی متن جایگزین و عنوان یک رسانه
 */
function updateMedia(int $id, array $data): bool
{
    $stmt = Database::getConnection()->prepare(
        'UPDATE ' . tbl('media') . ' SET alt_text = ?, caption = ? WHERE id = ?'
    );
    $stmt->execute([
        mb_substr(trim((string) ($data['alt_text'] ?? '')), 0, 255, 'UTF-8'),
        mb_substr(trim((string) ($data['caption'] ?? '')), 0, 500, 'UTF-8'),
        $id,
    ]);

    return $stmt->rowCount() >= 0;
}

/**
 * حذف یک رسانه همراه با فایل فیزیکی
 */
function deleteMedia(int $id): bool
{
    $db = Database::getConnection();

    $item = getMediaItem($id);
    if ($item === null) {
        return false;
    }

    $stmt = $db->prepare('DELETE FROM ' . tbl('media') . ' WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        return false;
    }

    // حذف فایل اصلی و بندانگشتی — مسیر باید داخل پوشه uploads بماند
    foreach ([$item['file_path'], $item['thumbnail_path']] as $path) {
        if (empty($path)) {
            continue;
        }

        $fullPath = realpath(UPLOADS_PATH . '/' . $path);

        if ($fullPath !== false && str_starts_with($fullPath, realpath(UPLOADS_PATH) ?: UPLOADS_PATH)) {
            @unlink($fullPath);
        }
    }

    logActivity('delete_media', 'media', $id, 'حذف فایل: ' . $item['original_name']);

    return true;
}

/**
 * ساخت تصویر بندانگشتی
 *
 * @return string|null مسیر نسبی بندانگشتی
 */
function createThumbnail(string $sourcePath, string $subDir, string $fileName): ?string
{
    if (!extension_loaded('gd')) {
        return null;
    }

    $info = @getimagesize($sourcePath);
    if ($info === false) {
        return null;
    }

    [$srcWidth, $srcHeight] = $info;
    $mime = $info['mime'];

    $source = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png'  => @imagecreatefrompng($sourcePath),
        'image/gif'  => @imagecreatefromgif($sourcePath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default      => false,
    };

    if ($source === false || $source === null) {
        return null;
    }

    // محاسبه ابعاد با حفظ نسبت تصویر
    $ratio = min(THUMBNAIL_WIDTH / $srcWidth, THUMBNAIL_HEIGHT / $srcHeight, 1);
    $newWidth = max(1, (int) round($srcWidth * $ratio));
    $newHeight = max(1, (int) round($srcHeight * $ratio));

    $thumb = imagecreatetruecolor($newWidth, $newHeight);

    // حفظ شفافیت برای PNG و GIF
    if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
    }

    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

    $thumbName = 'thumb-' . $fileName;
    $thumbPath = UPLOADS_PATH . '/' . $subDir . '/' . $thumbName;

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($thumb, $thumbPath, 82),
        'image/png'  => imagepng($thumb, $thumbPath, 8),
        'image/gif'  => imagegif($thumb, $thumbPath),
        'image/webp' => function_exists('imagewebp') && imagewebp($thumb, $thumbPath, 82),
        default      => false,
    };

    imagedestroy($thumb);
    imagedestroy($source);

    return $saved ? $subDir . '/' . $thumbName : null;
}

/**
 * پاک‌سازی محتوای SVG از اسکریپت و رویدادها
 *
 * @return string|null null اگر فایل SVG معتبر نباشد
 */
function sanitizeSvg(string $content): ?string
{
    if (!str_contains($content, '<svg')) {
        return null;
    }

    // حذف اعلان DOCTYPE و ENTITY (جلوگیری از XXE)
    $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
    $content = preg_replace('/<!ENTITY[^>]*>/i', '', $content);

    // حذف تگ‌های اجرایی
    $content = preg_replace('#<\s*(script|foreignObject|use|handler|set|animate)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $content);
    $content = preg_replace('#<\s*(script|foreignObject|handler)\b[^>]*/?>#i', '', $content);

    // حذف رویدادها و پروتکل‌های خطرناک
    $content = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $content);
    $content = preg_replace('/(href|xlink:href|src)\s*=\s*("|\')\s*(javascript|data\s*:\s*text\/html)[^"\']*\2/i', '', $content);

    return trim($content);
}

/**
 * افزودن فیلدهای محاسبه‌شده به رکورد رسانه
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function formatMediaRow(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['file_size'] = (int) $row['file_size'];
    $row['width'] = $row['width'] !== null ? (int) $row['width'] : null;
    $row['height'] = $row['height'] !== null ? (int) $row['height'] : null;
    $row['url'] = UPLOADS_URL . '/' . $row['file_path'];
    $row['thumbnail_url'] = !empty($row['thumbnail_path'])
        ? UPLOADS_URL . '/' . $row['thumbnail_path']
        : $row['url'];
    $row['is_image'] = str_starts_with((string) $row['mime_type'], 'image/');
    $row['size_label'] = formatBytes($row['file_size']);
    $row['date_jalali'] = jalaliDate('j F Y', $row['created_at']);

    return $row;
}

/**
 * پیام خطای آپلود
 */
function uploadErrorMessage(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است',
        UPLOAD_ERR_PARTIAL    => 'فایل به‌طور کامل آپلود نشد',
        UPLOAD_ERR_NO_FILE    => 'فایلی انتخاب نشده است',
        UPLOAD_ERR_NO_TMP_DIR => 'پوشه موقت سرور یافت نشد',
        UPLOAD_ERR_CANT_WRITE => 'نوشتن فایل روی دیسک ممکن نشد',
        UPLOAD_ERR_EXTENSION  => 'یک افزونه PHP آپلود را متوقف کرد',
        default               => 'خطای ناشناخته در آپلود',
    };
}
