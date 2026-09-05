<?php
/**
 * سربرگ سایت
 *
 * @var array<string, mixed> $GLOBALS['fardcms_view']
 */

$settings = getSiteSettings();
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
<body>
  <a class="skip-link" href="#main">پرش به محتوای اصلی</a>

  <header class="site-header">
    <div class="container">
      <div class="header-inner">
        <a class="site-brand" href="<?= e(routeUrl('')) ?>">
          <?php if (!empty($settings['site_logo'])): ?>
            <img class="brand-logo" src="<?= e($settings['site_logo']) ?>"
                 alt="<?= e($settings['site_title']) ?>">
          <?php else: ?>
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span>
              <span class="brand-name"><?= e($settings['site_title']) ?></span>
              <?php if (!empty($settings['site_tagline'])): ?>
                <span class="brand-tagline"><?= e($settings['site_tagline']) ?></span>
              <?php endif; ?>
            </span>
          <?php endif; ?>
        </a>

        <nav class="site-nav" id="site-nav" aria-label="فهرست اصلی">
          <?php
          $primaryMenu = getMenuByLocation('primary');

          if (!empty($primaryMenu)) {
              renderMenu('primary');
          } else {
              // اگر فهرستی ساخته نشده باشد، پیوندهای پایه نمایش داده می‌شود
              echo '<ul class="nav-menu">';
              echo '<li><a href="' . e(routeUrl('')) . '">خانه</a></li>';
              echo '<li><a href="' . e(routeUrl('blog')) . '">وبلاگ</a></li>';
              echo '</ul>';
          }
          ?>
        </nav>

        <div class="header-actions">
          <button class="icon-button" type="button" id="search-toggle"
                  aria-expanded="false" aria-controls="header-search" aria-label="جستجو">
            <svg width="19" height="19" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
            </svg>
          </button>

          <button class="icon-button" type="button" id="theme-toggle" aria-label="تغییر پوسته">
            <svg class="theme-icon-dark" width="19" height="19" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>
            </svg>
            <svg class="theme-icon-light" width="19" height="19" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="display:none">
              <circle cx="12" cy="12" r="4"/>
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
          </button>

          <?php if ($currentUser !== null): ?>
            <?php if (canAccessAdmin()): ?>
              <a class="btn btn-outline btn-sm" href="<?= e(assetUrl('admin/')) ?>">پیشخوان</a>
            <?php else: ?>
              <span class="text-muted" style="font-size:13px"><?= e($currentUser['name']) ?></span>
            <?php endif; ?>
          <?php else: ?>
            <a class="btn btn-outline btn-sm" href="<?= e(assetUrl('login.php')) ?>">ورود</a>
          <?php endif; ?>

          <button class="icon-button nav-toggle" type="button" id="nav-toggle"
                  aria-expanded="false" aria-controls="site-nav" aria-label="فهرست">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="header-search" id="header-search">
        <form class="search-form" action="<?= e(routeUrl('search')) ?>" method="get" role="search">
          <input class="search-input" type="search" name="q" id="search-field"
                 placeholder="در سایت جستجو کنید…"
                 value="<?= e((string) view('query', '')) ?>"
                 aria-label="عبارت جستجو">
          <button class="btn btn-primary" type="submit">جستجو</button>
        </form>
      </div>
    </div>
  </header>

  <main class="site-main" id="main">
