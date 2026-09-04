<?php
/**
 * صفحه حالت تعمیر و نگهداری
 *
 * این صفحه عمداً مستقل از سربرگ و پاورقی است تا در زمان تعمیر سایت
 * به فهرست‌ها و تنظیمات دیتابیس وابستگی کمتری داشته باشد.
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
      <div class="empty-icon" style="width:78px;height:78px;margin-bottom:24px">
        <svg width="36" height="36" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.8-3.8a6 6 0 01-7.9 7.9l-6.9 6.9a2.1 2.1 0 01-3-3l6.9-6.9a6 6 0 017.9-7.9l-3.8 3.8z"/>
        </svg>
      </div>

      <h1 class="page-heading">به‌زودی برمی‌گردیم</h1>
      <p class="empty-text">
        در حال انجام کارهای فنی روی <strong><?= e($settings['site_title']) ?></strong> هستیم.
        لطفاً کمی بعد دوباره سر بزنید.
      </p>

      <a class="btn btn-outline" href="<?= e(assetUrl('login.html')) ?>">ورود مدیران</a>
    </div>
  </div>
</body>
</html>
