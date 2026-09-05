<?php
/**
 * کارت یک نمونه‌کار
 *
 * @var array<string, mixed> $post
 */

$thumb = postThumbnail($post);
$meta = getAllPostMeta((int) $post['id']);
$subtitle = (string) ($meta['subtitle'] ?? '');
// نشان‌های «گالری» و «ویدیو» فقط وقتی معنا دارند که افزونه گالری فعال
// باشد؛ صافی در نبود افزونه خالی برمی‌گرداند
$galleryCount = count(applyFilters('post_gallery', [], (int) $post['id']));
$hasVideo = applyFilters('post_video', null, (int) $post['id']) !== null;
// نشانی را خود هسته می‌سازد؛ ساختن دستی، حالت نشانی پرسمانی را می‌شکند
$url = (string) ($post['url'] ?? routeUrl('blog/' . $post['slug']));
?>
<article class="work-card">
  <a class="work-media" href="<?= e($url) ?>"
     aria-label="<?= e($post['title']) ?>">
    <?php if ($thumb !== ''): ?>
      <img src="<?= e($thumb) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async">
    <?php else: ?>
      <span class="work-placeholder" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"
             stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="14" rx="2"/>
          <circle cx="8.5" cy="10" r="1.6"/><path d="M21 16l-5-5-6 6"/>
        </svg>
      </span>
    <?php endif; ?>

    <?php if ($hasVideo || $galleryCount > 1): ?>
      <span class="work-badges">
        <?php if ($hasVideo): ?>
          <span class="work-badge" title="دارای ویدیو">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M8 5.5v13l10-6.5z"/>
            </svg>
            ویدیو
          </span>
        <?php endif; ?>

        <?php if ($galleryCount > 1): ?>
          <span class="work-badge" title="تعداد تصاویر گالری">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="7" y="3" width="14" height="14" rx="2"/><path d="M3 7v12a2 2 0 002 2h12"/>
            </svg>
            <?= e(toPersianDigits((string) $galleryCount)) ?>
          </span>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </a>

  <div class="work-body">
    <?php if (!empty($post['categories'])): ?>
      <div class="work-terms">
        <?php foreach (array_slice($post['categories'], 0, 2) as $category): ?>
          <a class="term-chip" href="<?= e(routeUrl('category/' . $category['slug'])) ?>">
            <?= e($category['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h3 class="work-title">
      <a href="<?= e($url) ?>"><?= e($post['title']) ?></a>
    </h3>

    <?php if ($subtitle !== ''): ?>
      <p class="work-subtitle"><?= e($subtitle) ?></p>
    <?php elseif (!empty($post['excerpt'])): ?>
      <p class="work-subtitle"><?= e($post['excerpt']) ?></p>
    <?php endif; ?>
  </div>
</article>
