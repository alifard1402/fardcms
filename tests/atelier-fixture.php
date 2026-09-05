<?php
/**
 * داده آزمایشی قالب آتلیه
 *
 *   php tests/atelier-fixture.php --create
 *   php tests/atelier-fixture.php --remove
 *
 * یک نمونه‌کار با گالری چهار تصویری و ویدیوی آپارات می‌سازد تا تست قالب
 * به داده‌ای که ممکن است در دیتابیس باشد یا نباشد وابسته نماند.
 * تصویرها همین‌جا ساخته می‌شوند، پس نیازی به فایل باینری در مخزن نیست.
 */

require dirname(__DIR__) . '/config.php';

// فیلدهای گالری و ویدیو متعلق به افزونه‌اند: بدون بارگذاری افزونه‌ها،
// صافی post_meta_input وجود ندارد و meta ذخیره نمی‌شود
if (!in_array('gallery', activePlugins(), true)) {
    setOption('active_plugins', array_merge(activePlugins(), ['gallery']));
}

loadPlugins();

const FIXTURE_SLUG = 'atelier-test-fixture';
const FIXTURE_DIR  = 'atelier-test';

/** تصویر ساده با طیف رنگی، به‌جای عکس واقعی */
function fixtureImage(string $path, int $seed): void
{
    $w = 900;
    $h = 1200;
    $img = imagecreatetruecolor($w, $h);

    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $color = imagecolorallocate(
            $img,
            (int) (214 - 76 * $t),
            (int) (188 - 82 * $t),
            (int) (160 - 81 * $t)
        );
        imageline($img, 0, $y, $w, $y, $color);
    }

    // نقش کوچکی که هر تصویر را از بقیه جدا کند
    $mark = imagecolorallocatealpha($img, 255, 255, 255, 90);
    imagefilledellipse($img, 150 + $seed * 170, 400 + $seed * 120, 260, 260, $mark);

    imagejpeg($img, $path, 80);
    imagedestroy($img);
}

$db = Database::getConnection();
$mode = $argv[1] ?? '--create';

/* ─── پاک‌سازی ─────────────────────────────────────────── */
if ($mode === '--remove') {
    $post = getPostBySlug(FIXTURE_SLUG, 'post', ['publish', 'draft', 'pending', 'private']);

    if ($post !== null) {
        deletePost((int) $post['id'], true);
    }

    $stmt = $db->prepare('SELECT id, file_path FROM ' . tbl('media') . ' WHERE file_path LIKE ?');
    $stmt->execute([FIXTURE_DIR . '/%']);

    foreach ($stmt->fetchAll() as $row) {
        @unlink(UPLOADS_PATH . '/' . $row['file_path']);
        $db->prepare('DELETE FROM ' . tbl('media') . ' WHERE id = ?')->execute([$row['id']]);
    }

    @rmdir(UPLOADS_PATH . '/' . FIXTURE_DIR);

    echo "داده آزمایشی قالب حذف شد.\n";
    exit(0);
}

/* ─── ساخت ─────────────────────────────────────────────── */
$dir = UPLOADS_PATH . '/' . FIXTURE_DIR;
@mkdir($dir, 0755, true);

$userId = (int) $db->query('SELECT id FROM ' . tbl('users') . ' ORDER BY id LIMIT 1')->fetchColumn();
$ids = [];

for ($i = 1; $i <= 4; $i++) {
    $name = "atelier-$i.jpg";
    $file = "$dir/$name";
    fixtureImage($file, $i);

    $stmt = $db->prepare('INSERT INTO ' . tbl('media') .
        ' (user_id, file_name, file_path, thumbnail_path, original_name, mime_type,
           file_size, width, height, alt_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $name, FIXTURE_DIR . "/$name", FIXTURE_DIR . "/$name", $name,
        'image/jpeg', filesize($file), 900, 1200, "تصویر آزمایشی $i"]);

    $ids[] = (int) $db->lastInsertId();
}

$result = savePost([
    'title'          => 'نمونه‌کار آزمایشی آتلیه',
    'slug'           => FIXTURE_SLUG,
    'type'           => 'post',
    'status'         => 'publish',
    'content'        => '<p>متن آزمایشی برای سنجش قالب.</p>',
    'featured_image' => UPLOADS_URL . '/' . FIXTURE_DIR . '/atelier-1.jpg',
    'meta'           => [
        'gallery'        => $ids,
        'subtitle'       => 'زیرعنوان آزمایشی',
        'shoot_location' => 'محل آزمایشی',
        'video_provider' => 'aparat',
        'video_aparat'   => 'testhash',
    ],
]);

if (!$result['success']) {
    fwrite(STDERR, 'ساخت نمونه‌کار آزمایشی شکست خورد: ' . $result['message'] . "\n");
    exit(1);
}

// در خط فرمان کاربری وارد نشده است، پس savePost وضعیت را «در انتظار»
// می‌گذارد؛ برای دیده شدن در سایت باید منتشر شود.
$db->prepare('UPDATE ' . tbl('posts') . " SET status = 'publish',
              published_at = COALESCE(published_at, NOW()) WHERE id = ?")
   ->execute([$result['data']['post']['id']]);

recountAllTerms();

echo "داده آزمایشی قالب ساخته شد (شناسه {$result['data']['post']['id']}).\n";
