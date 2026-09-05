<?php
/**
 * سربرگ قالب آتلیه
 *
 * سربرگ روی صفحه اصلی شفاف است و روی تصویر بزرگ می‌نشیند؛ در بقیه
 * صفحه‌ها زمینه دارد. با اسکرول هم زمینه‌دار می‌شود تا متنش خوانا بماند.
 */

$settings = getSiteSettings();
$currentUser = getCurrentUser();
$overlay = (bool) view('has_hero', false);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#faf7f3">
  <?php renderMetaTags(); ?>

  <?php if (!empty($settings['site_favicon'])): ?>
    <link rel="icon" href="<?= e($settings['site_favicon']) ?>">
  <?php else: ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(assetUrl('assets/favicon.svg')) ?>">
  <?php endif; ?>

  <link rel="stylesheet" href="<?= e(assetUrl('assets/css/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= e(themeUrl('assets/style.css')) ?>">
  <link rel="alternate" type="application/rss+xml"
        title="<?= e($settings['site_title']) ?>" href="<?= e(routeUrl('feed')) ?>">

  <?php if (!empty($settings['google_analytics'])): ?>
    <?php /* شناسه از تنظیمات می‌آید و در ذخیره‌سازی به الگوی مجاز محدود شده است */ ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($settings['google_analytics']) ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', <?= json_encode($settings['google_analytics'], JSON_UNESCAPED_SLASHES) ?>);
    </script>
  <?php endif; ?>
</head>
<body class="<?= $overlay ? 'has-overlay-header' : '' ?>">
  <a class="skip-link" href="#main">پرش به محتوای اصلی</a>

  <header class="site-header<?= $overlay ? ' site-header--overlay' : '' ?>" id="site-header">
    <div class="container header-inner">
      <a class="brand" href="<?= e(routeUrl('')) ?>">
        <?php if (!empty($settings['site_logo'])): ?>
          <img class="brand-logo" src="<?= e($settings['site_logo']) ?>"
               alt="<?= e($settings['site_title']) ?>">
        <?php else: ?>
          <span class="brand-name"><?= e($settings['site_title']) ?></span>
        <?php endif; ?>
      </a>

      <nav class="site-nav" id="site-nav" aria-label="فهرست اصلی">
        <?php
        if (!empty(getMenuByLocation('primary'))) {
            renderMenu('primary');
        } else {
            // تا وقتی فهرستی ساخته نشده، پیوندهای پایه نمایش داده می‌شود
            echo '<ul class="nav-menu">';
            echo '<li><a href="' . e(routeUrl('')) . '">خانه</a></li>';
            echo '<li><a href="' . e(routeUrl('blog')) . '">نمونه‌کارها</a></li>';
            echo '</ul>';
        }
        ?>
      </nav>

      <div class="header-actions">
        <a class="icon-button" href="<?= e(routeUrl('search')) ?>" aria-label="جستجو">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
               stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
          </svg>
        </a>

        <?php if ($currentUser !== null && canAccessAdmin()): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(assetUrl('admin/')) ?>">پیشخوان</a>
        <?php endif; ?>

        <button class="icon-button nav-toggle" type="button" id="nav-toggle"
                aria-expanded="false" aria-controls="site-nav" aria-label="فهرست">
          <svg class="icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
          </svg>
          <svg class="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="1.7" stroke-linecap="round" aria-hidden="true">
            <path d="M6 6l12 12M18 6L6 18"/>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <div class="nav-backdrop" id="nav-backdrop"></div>

  <main class="site-main" id="main">
