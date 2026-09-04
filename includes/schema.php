<?php
/**
 * ساختار جداول دیتابیس
 *
 * این فایل تنها منبع تعریف جداول است و توسط نصب‌کننده و تست‌ها
 * استفاده می‌شود تا ساختار در همه‌جا یکسان بماند.
 */

/**
 * تعریف تمام جداول به ترتیب وابستگی
 *
 * @return array<string, string> نام جدول (بدون پیشوند) => دستور CREATE TABLE
 */
function schemaTables(): array
{
    $charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    return [
        // ─── کاربران ───────────────────────────────────────
        'users' => 'CREATE TABLE IF NOT EXISTS ' . tbl('users') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL DEFAULT '',
            `username` VARCHAR(50) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `role` VARCHAR(20) NOT NULL DEFAULT 'subscriber',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `avatar` VARCHAR(500) DEFAULT NULL,
            `bio` VARCHAR(500) NOT NULL DEFAULT '',
            `last_login_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_email` (`email`),
            UNIQUE KEY `uk_username` (`username`),
            KEY `idx_role` (`role`)
        ) $charset",

        // ─── نشست‌ها ───────────────────────────────────────
        'sessions' => 'CREATE TABLE IF NOT EXISTS ' . tbl('sessions') . " (
            `id` VARCHAR(128) NOT NULL PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `remember_token` VARCHAR(64) DEFAULT NULL,
            KEY `idx_user` (`user_id`),
            KEY `idx_last_activity` (`last_activity`),
            KEY `idx_remember` (`remember_token`),
            CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE CASCADE
        ) $charset",

        // ─── بازیابی رمز عبور ──────────────────────────────
        'password_resets' => 'CREATE TABLE IF NOT EXISTS ' . tbl('password_resets') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_token` (`token`),
            KEY `idx_expires` (`expires_at`),
            CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE CASCADE
        ) $charset",

        // ─── تنظیمات سایت ──────────────────────────────────
        'options' => 'CREATE TABLE IF NOT EXISTS ' . tbl('options') . " (
            `option_name` VARCHAR(100) NOT NULL PRIMARY KEY,
            `option_value` LONGTEXT DEFAULT NULL,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset",

        // ─── محتوا (نوشته‌ها و برگه‌ها) ─────────────────────
        'posts' => 'CREATE TABLE IF NOT EXISTS ' . tbl('posts') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `author_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(250) NOT NULL,
            `slug` VARCHAR(200) NOT NULL,
            `content` LONGTEXT DEFAULT NULL,
            `excerpt` VARCHAR(500) NOT NULL DEFAULT '',
            `type` VARCHAR(20) NOT NULL DEFAULT 'post',
            `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
            `featured_image` VARCHAR(500) DEFAULT NULL,
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `menu_order` INT NOT NULL DEFAULT 0,
            `template` VARCHAR(100) DEFAULT NULL,
            `comment_status` VARCHAR(10) NOT NULL DEFAULT 'open',
            `views` INT UNSIGNED NOT NULL DEFAULT 0,
            `published_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_slug` (`slug`),
            KEY `idx_type_status` (`type`, `status`),
            KEY `idx_author` (`author_id`),
            KEY `idx_published` (`published_at`),
            KEY `idx_parent` (`parent_id`),
            CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE SET NULL
        ) $charset",

        // ─── فیلدهای سفارشی ────────────────────────────────
        'postmeta' => 'CREATE TABLE IF NOT EXISTS ' . tbl('postmeta') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id` INT UNSIGNED NOT NULL,
            `meta_key` VARCHAR(100) NOT NULL,
            `meta_value` LONGTEXT DEFAULT NULL,
            UNIQUE KEY `uk_post_key` (`post_id`, `meta_key`),
            CONSTRAINT `fk_postmeta_post` FOREIGN KEY (`post_id`)
                REFERENCES " . tbl('posts') . " (`id`) ON DELETE CASCADE
        ) $charset",

        // ─── دسته‌ها و برچسب‌ها ────────────────────────────
        'terms' => 'CREATE TABLE IF NOT EXISTS ' . tbl('terms') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(200) NOT NULL,
            `taxonomy` VARCHAR(20) NOT NULL DEFAULT 'category',
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_slug_taxonomy` (`slug`, `taxonomy`),
            KEY `idx_taxonomy` (`taxonomy`),
            KEY `idx_parent` (`parent_id`)
        ) $charset",

        // ─── رابطه محتوا و ترم‌ها ──────────────────────────
        'term_relationships' => 'CREATE TABLE IF NOT EXISTS ' . tbl('term_relationships') . " (
            `post_id` INT UNSIGNED NOT NULL,
            `term_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`post_id`, `term_id`),
            KEY `idx_term` (`term_id`),
            CONSTRAINT `fk_tr_post` FOREIGN KEY (`post_id`)
                REFERENCES " . tbl('posts') . " (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tr_term` FOREIGN KEY (`term_id`)
                REFERENCES " . tbl('terms') . " (`id`) ON DELETE CASCADE
        ) $charset",

        // ─── دیدگاه‌ها ─────────────────────────────────────
        'comments' => 'CREATE TABLE IF NOT EXISTS ' . tbl('comments') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `post_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `author_name` VARCHAR(100) NOT NULL DEFAULT '',
            `author_email` VARCHAR(255) NOT NULL DEFAULT '',
            `content` TEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `user_agent` VARCHAR(255) DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_post_status` (`post_id`, `status`),
            KEY `idx_status` (`status`),
            KEY `idx_parent` (`parent_id`),
            CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`)
                REFERENCES " . tbl('posts') . " (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE SET NULL
        ) $charset",

        // ─── کتابخانه رسانه ────────────────────────────────
        'media' => 'CREATE TABLE IF NOT EXISTS ' . tbl('media') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `thumbnail_path` VARCHAR(500) DEFAULT NULL,
            `original_name` VARCHAR(255) NOT NULL DEFAULT '',
            `mime_type` VARCHAR(100) NOT NULL DEFAULT '',
            `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
            `width` INT UNSIGNED DEFAULT NULL,
            `height` INT UNSIGNED DEFAULT NULL,
            `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
            `caption` VARCHAR(500) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_user` (`user_id`),
            KEY `idx_mime` (`mime_type`),
            CONSTRAINT `fk_media_user` FOREIGN KEY (`user_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE SET NULL
        ) $charset",

        // ─── فهرست‌های ناوبری ──────────────────────────────
        'menus' => 'CREATE TABLE IF NOT EXISTS ' . tbl('menus') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(200) NOT NULL,
            `location` VARCHAR(30) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_slug` (`slug`),
            KEY `idx_location` (`location`)
        ) $charset",

        'menu_items' => 'CREATE TABLE IF NOT EXISTS ' . tbl('menu_items') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `menu_id` INT UNSIGNED NOT NULL,
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `title` VARCHAR(100) NOT NULL,
            `url` VARCHAR(500) NOT NULL DEFAULT '',
            `type` VARCHAR(20) NOT NULL DEFAULT 'custom',
            `object_id` INT UNSIGNED DEFAULT NULL,
            `target` VARCHAR(10) NOT NULL DEFAULT '',
            `icon` VARCHAR(50) NOT NULL DEFAULT '',
            `menu_order` INT NOT NULL DEFAULT 0,
            KEY `idx_menu` (`menu_id`),
            KEY `idx_parent` (`parent_id`),
            CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`)
                REFERENCES " . tbl('menus') . " (`id`) ON DELETE CASCADE
        ) $charset",

        // ─── گزارش فعالیت‌ها ───────────────────────────────
        'activity_log' => 'CREATE TABLE IF NOT EXISTS ' . tbl('activity_log') . " (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(50) NOT NULL,
            `object_type` VARCHAR(30) NOT NULL DEFAULT '',
            `object_id` INT UNSIGNED DEFAULT NULL,
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`),
            CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`)
                REFERENCES " . tbl('users') . " (`id`) ON DELETE SET NULL
        ) $charset",
    ];
}

/**
 * ساخت تمام جداول
 *
 * @return string[] نام جداول ساخته‌شده
 */
function createSchema(PDO $db): array
{
    $created = [];

    foreach (schemaTables() as $name => $sql) {
        $db->exec($sql);
        $created[] = DB_PREFIX . $name;
    }

    return $created;
}

/**
 * حذف تمام جداول (برای تست و نصب مجدد)
 */
function dropSchema(PDO $db): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach (array_reverse(array_keys(schemaTables())) as $name) {
        $db->exec('DROP TABLE IF EXISTS ' . tbl($name));
    }

    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/**
 * درج داده‌های اولیه: تنظیمات، دسته پیش‌فرض، محتوای نمونه و فهرست
 */
function seedInitialData(PDO $db, int $adminId): void
{
    // ─── تنظیمات پیش‌فرض ───────────────────────────────────
    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('options') . ' (option_name, option_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)'
    );

    foreach (defaultOptions() as $name => $value) {
        $stmt->execute([$name, json_encode($value, JSON_UNESCAPED_UNICODE)]);
    }

    // ─── دسته‌بندی‌های نمونه ───────────────────────────────
    $categories = [
        ['دسته‌بندی نشده', 'uncategorized', 'نوشته‌هایی که هنوز دسته‌بندی نشده‌اند'],
        ['فناوری', 'technology', 'اخبار و مقالات دنیای فناوری'],
        ['آموزش', 'tutorials', 'راهنماها و آموزش‌های گام‌به‌گام'],
    ];

    $termStmt = $db->prepare(
        'INSERT IGNORE INTO ' . tbl('terms') . " (name, slug, taxonomy, description) VALUES (?, ?, 'category', ?)"
    );

    $categoryIds = [];
    foreach ($categories as [$name, $slug, $description]) {
        $termStmt->execute([$name, $slug, $description]);
        $id = (int) $db->lastInsertId();

        if ($id === 0) {
            $find = $db->prepare('SELECT id FROM ' . tbl('terms') . " WHERE slug = ? AND taxonomy = 'category'");
            $find->execute([$slug]);
            $id = (int) ($find->fetch()['id'] ?? 0);
        }

        $categoryIds[$slug] = $id;
    }

    // ─── برچسب‌های نمونه ───────────────────────────────────
    $tagStmt = $db->prepare(
        'INSERT IGNORE INTO ' . tbl('terms') . " (name, slug, taxonomy) VALUES (?, ?, 'tag')"
    );

    foreach ([['وی‌جی‌اس', 'vuejs'], ['پی‌اچ‌پی', 'php'], ['طراحی وب', 'web-design']] as [$name, $slug]) {
        $tagStmt->execute([$name, $slug]);
    }

    // ─── محتوای نمونه ──────────────────────────────────────
    $samplePosts = [
        [
            'title'   => 'به فرد سی‌ام‌اس خوش آمدید',
            'slug'    => 'welcome-to-fardcms',
            'type'    => 'post',
            'excerpt' => 'اولین نوشته سایت شما. می‌توانید آن را ویرایش یا حذف کنید و نوشتن را آغاز کنید.',
            'content' => '<h2>سلام!</h2>'
                . '<p>این اولین نوشته سایت شماست. <strong>فرد سی‌ام‌اس</strong> یک سیستم مدیریت محتوای سبک،'
                . ' سریع و کاملاً فارسی است که با <em>Vue 3</em> و <em>PHP 8</em> ساخته شده است.</p>'
                . '<h3>از کجا شروع کنم؟</h3>'
                . '<ul>'
                . '<li>از پنل مدیریت، بخش <strong>نوشته‌ها</strong> را باز کنید و این نوشته را ویرایش کنید.</li>'
                . '<li>در بخش <strong>تنظیمات</strong> عنوان و شعار سایت را تغییر دهید.</li>'
                . '<li>در بخش <strong>فهرست‌ها</strong> منوی سربرگ سایت را بسازید.</li>'
                . '<li>در <strong>کتابخانه رسانه</strong> تصاویر خود را آپلود کنید.</li>'
                . '</ul>'
                . '<blockquote>هر چیزی که در این نوشته می‌بینید از پنل مدیریت قابل تغییر است.</blockquote>',
            'category' => 'uncategorized',
        ],
        [
            'title'   => 'راهنمای نوشتن اولین مقاله',
            'slug'    => 'writing-your-first-article',
            'type'    => 'post',
            'excerpt' => 'با ویرایشگر محتوا، دسته‌بندی‌ها و برچسب‌ها آشنا شوید و اولین مقاله خود را منتشر کنید.',
            'content' => '<p>در فرد سی‌ام‌اس نوشتن یک مقاله ساده است. کافی است به بخش'
                . ' <strong>نوشته‌ها → افزودن نوشته</strong> بروید.</p>'
                . '<h3>ویرایشگر محتوا</h3>'
                . '<p>ویرایشگر از قالب‌بندی متن، عنوان‌ها، فهرست‌ها، نقل‌قول، لینک، تصویر و کد پشتیبانی می‌کند.'
                . ' می‌توانید بین حالت بصری و حالت HTML جابه‌جا شوید.</p>'
                . '<h3>دسته‌بندی و برچسب</h3>'
                . '<p>هر نوشته می‌تواند به چند <strong>دسته</strong> تعلق داشته باشد و چند <strong>برچسب</strong> بگیرد.'
                . ' برچسب‌های جدید به‌صورت خودکار ساخته می‌شوند.</p>'
                . '<h3>پیش‌نویس و انتشار</h3>'
                . '<p>می‌توانید نوشته را به‌عنوان پیش‌نویس ذخیره کنید و بعداً منتشر کنید، یا زمان انتشار'
                . ' آینده برای آن تعیین کنید.</p>',
            'category' => 'tutorials',
        ],
    ];

    $samplePages = [
        [
            'title'   => 'درباره ما',
            'slug'    => 'about',
            'type'    => 'page',
            'excerpt' => 'معرفی کوتاهی از ما و اهدافمان.',
            'content' => '<h2>درباره ما</h2>'
                . '<p>این یک برگه نمونه است. برگه‌ها بر خلاف نوشته‌ها تاریخ‌محور نیستند و برای محتوای'
                . ' ثابت مانند «درباره ما»، «تماس با ما» یا «قوانین» مناسب‌اند.</p>'
                . '<p>برای ویرایش این برگه به پنل مدیریت، بخش <strong>برگه‌ها</strong> بروید.</p>',
        ],
        [
            'title'   => 'تماس با ما',
            'slug'    => 'contact',
            'type'    => 'page',
            'excerpt' => 'راه‌های ارتباط با ما.',
            'content' => '<h2>تماس با ما</h2>'
                . '<p>برای ارتباط با ما می‌توانید از راه‌های زیر استفاده کنید:</p>'
                . '<ul><li>ایمیل: info@example.com</li><li>تلفن: ۰۲۱-۱۲۳۴۵۶۷۸</li></ul>',
        ],
    ];

    $postStmt = $db->prepare(
        'INSERT IGNORE INTO ' . tbl('posts') . " (author_id, title, slug, content, excerpt, type,
            status, comment_status, published_at)
         VALUES (?, ?, ?, ?, ?, ?, 'publish', 'open', NOW())"
    );

    $relStmt = $db->prepare(
        'INSERT IGNORE INTO ' . tbl('term_relationships') . ' (post_id, term_id) VALUES (?, ?)'
    );

    $pageIds = [];

    foreach (array_merge($samplePosts, $samplePages) as $item) {
        $postStmt->execute([
            $adminId,
            $item['title'],
            $item['slug'],
            $item['content'],
            $item['excerpt'],
            $item['type'],
        ]);

        $postId = (int) $db->lastInsertId();

        if ($postId === 0) {
            continue;   // از قبل وجود داشته است
        }

        if ($item['type'] === 'page') {
            $pageIds[$item['slug']] = $postId;
        }

        if (!empty($item['category']) && !empty($categoryIds[$item['category']])) {
            $relStmt->execute([$postId, $categoryIds[$item['category']]]);
        }
    }

    // ─── فهرست ناوبری اصلی ─────────────────────────────────
    $db->prepare('INSERT IGNORE INTO ' . tbl('menus') . " (name, slug, location)
                  VALUES ('فهرست اصلی', 'main-menu', 'primary')")->execute();

    $menuId = (int) $db->lastInsertId();

    if ($menuId > 0) {
        $itemStmt = $db->prepare(
            'INSERT INTO ' . tbl('menu_items') . ' (menu_id, title, url, type, object_id, menu_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        $itemStmt->execute([$menuId, 'خانه', siteUrl(''), 'custom', null, 0]);
        $itemStmt->execute([$menuId, 'وبلاگ', siteUrl('blog'), 'custom', null, 1]);

        $order = 2;
        foreach (['about' => 'درباره ما', 'contact' => 'تماس با ما'] as $slug => $title) {
            if (!empty($pageIds[$slug])) {
                $itemStmt->execute([$menuId, $title, '', 'page', $pageIds[$slug], $order++]);
            }
        }
    }

    // ─── بازشماری تعداد نوشته‌های هر دسته ──────────────────
    recountAllTerms();
}
