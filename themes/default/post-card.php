<?php
/**
 * کارت نمایش یک نوشته در فهرست‌ها
 *
 * @var array<string, mixed> $post
 */
?>
<article class="post-card">
  <?php if (!empty($post['featured_image'])): ?>
    <a class="post-card-media" href="<?= e($post['url']) ?>" aria-hidden="true" tabindex="-1">
      <img src="<?= e($post['featured_image']) ?>"
           alt="<?= e($post['title']) ?>" loading="lazy" width="640" height="360">
    </a>
  <?php endif; ?>

  <div class="post-card-body">
    <?php if (!empty($post['categories'])): ?>
      <div class="post-card-terms">
        <?php foreach (array_slice($post['categories'], 0, 3) as $category): ?>
          <a class="term-chip" href="<?= e(routeUrl('category/' . $category['slug'])) ?>">
            <?= e($category['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="post-card-title">
      <a href="<?= e($post['url']) ?>"><?= e($post['title']) ?></a>
    </h2>

    <?php if (!empty($post['excerpt'])): ?>
      <p class="post-card-excerpt"><?= e($post['excerpt']) ?></p>
    <?php endif; ?>

    <div class="post-card-meta">
      <span class="meta-item">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>
        </svg>
        <time datetime="<?= e(date('c', strtotime($post['published_at'] ?? $post['created_at']))) ?>">
          <?= e($post['date_jalali']) ?>
        </time>
      </span>

      <?php if (!empty($post['author_name'])): ?>
        <span class="meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>
          </svg>
          <?= e($post['author_name']) ?>
        </span>
      <?php endif; ?>

      <?php if (!empty($post['comment_count'])): ?>
        <span class="meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 12a8 8 0 01-8 8H8l-5 3v-4.5A8 8 0 0113 4a8 8 0 018 8z"/>
          </svg>
          <?= e(toPersianDigits((string) $post['comment_count'])) ?> دیدگاه
        </span>
      <?php endif; ?>
    </div>
  </div>
</article>
