<?php
/**
 * صفحه حالت تعمیر و نگهداری
 *
 * عمداً مستقل از سربرگ و پاورقی است تا در زمان تعمیر سایت به فهرست‌ها و
 * تنظیمات دیتابیس وابستگی کمتری داشته باشد.
 */

$settings = getSiteSettings();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>به‌زودی برمی‌گردیم — <?= e($settings['site_title']) ?></title>
  <link rel="stylesheet" href="<?= e(assetUrl('assets/css/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(themeUrl('assets/style.css')) ?>">
</head>
<body>
  <div class="container centered-page">
    <div>
      <h1 class="section-title"><?= e($settings['site_title']) ?></h1>
      <p class="empty-text">
        در حال آماده‌سازی مجموعه تازه‌ای هستیم. لطفاً کمی بعد دوباره سر بزنید.
      </p>
      <a class="btn btn-outline" href="<?= e(assetUrl('login.php')) ?>">ورود مدیران</a>
    </div>
  </div>
</body>
</html>
