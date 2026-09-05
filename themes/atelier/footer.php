<?php
/**
 * پاورقی قالب آتلیه
 */

$settings = getSiteSettings();
$social = (array) ($settings['social_links'] ?? []);
$socialLabels = [
    'instagram' => 'اینستاگرام',
    'telegram'  => 'تلگرام',
    'x'         => 'ایکس',
    'github'    => 'گیت‌هاب',
];
$activeSocial = array_filter($social, fn($url) => trim((string) $url) !== '');
?>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-brand">
          <span class="brand-name"><?= e($settings['site_title']) ?></span>
          <?php if (!empty($settings['site_tagline'])): ?>
            <p class="footer-tagline"><?= e($settings['site_tagline']) ?></p>
          <?php endif; ?>

          <?php if (!empty($activeSocial)): ?>
            <ul class="social-list">
              <?php foreach ($activeSocial as $key => $url): ?>
                <li>
                  <a href="<?= e((string) $url) ?>" target="_blank" rel="noopener noreferrer">
                    <?= e($socialLabels[$key] ?? $key) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <?php $footerMenu = getMenuByLocation('footer'); ?>
        <?php if (!empty($footerMenu)): ?>
          <nav class="footer-nav" aria-label="فهرست پاورقی">
            <?php renderMenu('footer'); ?>
          </nav>
        <?php endif; ?>

        <?php $categories = array_slice(getTerms('category', ['hide_empty' => true, 'orderby' => 'count', 'order' => 'desc']), 0, 8); ?>
        <?php if (!empty($categories)): ?>
          <nav class="footer-nav" aria-label="دسته‌ها">
            <h2 class="footer-heading">دسته‌ها</h2>
            <ul class="nav-menu">
              <?php foreach ($categories as $category): ?>
                <li>
                  <a href="<?= e(routeUrl('category/' . $category['slug'])) ?>">
                    <?= e($category['name']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </div>

      <div class="footer-bottom">
        <div>
          <?php if (!empty($settings['footer_text'])): ?>
            <?= $settings['footer_text'] /* در ذخیره‌سازی پاک‌سازی شده است */ ?>
          <?php else: ?>
            © <?= e(jalaliDate('Y')) ?> <?= e($settings['site_title']) ?> — تمام حقوق محفوظ است.
          <?php endif; ?>
        </div>

        <div>
          ساخته‌شده با <a href="https://github.com/alifard1402/fardcms" target="_blank"
                          rel="noopener">فرد سی‌ام‌اس</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- لایت‌باکس گالری؛ محتوایش را theme.js پر می‌کند -->
  <div class="lightbox" id="lightbox" role="dialog" aria-modal="true"
       aria-label="نمایش تصویر" hidden>
    <button class="lightbox-close" type="button" data-lightbox-close aria-label="بستن">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
           stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>

    <button class="lightbox-nav lightbox-prev" type="button" data-lightbox-prev aria-label="تصویر قبلی">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
           stroke-linecap="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>
    </button>

    <figure class="lightbox-figure">
      <img id="lightbox-image" src="" alt="">
      <figcaption id="lightbox-caption"></figcaption>
    </figure>

    <button class="lightbox-nav lightbox-next" type="button" data-lightbox-next aria-label="تصویر بعدی">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
           stroke-linecap="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
    </button>
  </div>

  <script src="<?= e(themeUrl('assets/theme.js')) ?>" defer></script>
</body>
</html>
