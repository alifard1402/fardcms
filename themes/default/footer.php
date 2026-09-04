<?php
/**
 * پاورقی سایت
 */

$settings = getSiteSettings();
$footerMenu = getMenuByLocation('footer');
$socialLinks = array_filter((array) $settings['social_links']);

/** آیکون هر شبکه اجتماعی */
$socialIcons = [
    'telegram'  => '<path d="M21.9 4.3L2.9 11.6c-1 .4-1 1 0 1.3l4.5 1.4 1.7 5.3c.2.6.4.7.9.3l2.5-2 4.6 3.4c.8.5 1.3.2 1.5-.7l3-14.1c.2-1-.4-1.5-1.7-1.2z"/>',
    'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/>',
    'x'         => '<path d="M4 4l7.2 9.3L4.4 20h2.3l5.6-5.6 4.3 5.6H20l-7.5-9.7L19.6 4h-2.3l-5.2 5.2L8.1 4H4z"/>',
    'github'    => '<path d="M9 19c-4 1.2-4-2-6-2.5m12 5v-3.9a3.4 3.4 0 00-.9-2.6c3-.4 6-1.5 6-6.6a5.2 5.2 0 00-1.4-3.6 4.8 4.8 0 00-.1-3.6s-1.5-.4-4.6 1.7a12.4 12.4 0 00-6.4 0C4.5 1.4 3 1.8 3 1.8a4.8 4.8 0 00-.1 3.6A5.2 5.2 0 001.5 9c0 5.1 3 6.2 6 6.6a3.4 3.4 0 00-.9 2.6V22"/>',
];

$socialLabels = [
    'telegram'  => 'تلگرام',
    'instagram' => 'اینستاگرام',
    'x'         => 'ایکس',
    'github'    => 'گیت‌هاب',
];

$categories = getTerms('category', ['hide_empty' => true]);
?>
  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-about">
          <a class="site-brand" href="<?= e(routeUrl('')) ?>">
            <span class="brand-mark" aria-hidden="true">✦</span>
            <span class="brand-name"><?= e($settings['site_title']) ?></span>
          </a>

          <p class="footer-about-text"><?= e($settings['site_description']) ?></p>

          <?php if (!empty($socialLinks)): ?>
            <div class="social-links">
              <?php foreach ($socialLinks as $network => $url): ?>
                <?php if (empty($socialIcons[$network])) { continue; } ?>
                <a class="social-link" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"
                   aria-label="<?= e($socialLabels[$network] ?? $network) ?>">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <?= $socialIcons[$network] ?>
                  </svg>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div>
          <h2 class="footer-heading">دسترسی سریع</h2>
          <ul class="footer-links">
            <?php if (!empty($footerMenu)): ?>
              <?php foreach ($footerMenu as $item): ?>
                <li>
                  <a href="<?= e($item['resolved_url']) ?>"
                     <?= $item['target'] === '_blank' ? 'target="_blank" rel="noopener"' : '' ?>>
                    <?= e($item['title']) ?>
                  </a>
                </li>
              <?php endforeach; ?>
            <?php else: ?>
              <li><a href="<?= e(routeUrl('')) ?>">خانه</a></li>
              <li><a href="<?= e(routeUrl('blog')) ?>">وبلاگ</a></li>
              <li><a href="<?= e(routeUrl('feed')) ?>">خوراک RSS</a></li>
              <li><a href="<?= e(routeUrl('sitemap.xml')) ?>">نقشه سایت</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <div>
          <h2 class="footer-heading">دسته‌بندی‌ها</h2>
          <?php if (!empty($categories)): ?>
            <ul class="footer-links">
              <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                <li>
                  <a href="<?= e($category['url']) ?>">
                    <?= e($category['name']) ?>
                    <span class="text-muted">(<?= e(toPersianDigits((string) $category['count'])) ?>)</span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="text-muted" style="font-size:14px">هنوز دسته‌بندی‌ای وجود ندارد.</p>
          <?php endif; ?>
        </div>
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

  <script src="<?= e(themeUrl('assets/theme.js')) ?>" defer></script>
</body>
</html>
