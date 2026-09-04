<?php
/**
 * فهرست نوشته‌ها (صفحه اصلی و وبلاگ)
 */

themePart('header');

$posts = (array) view('posts', []);
$meta = (array) view('meta', []);
?>
<div class="container">
  <?php if (view('archive_title')): ?>
    <header class="page-header <?= view('is_front') ? 'page-header--hero' : '' ?>">
      <h1 class="page-heading"><?= e((string) view('archive_title')) ?></h1>

      <?php if (view('is_front')): ?>
        <p class="page-subheading"><?= e((string) getOption('site_description', '')) ?></p>
      <?php elseif (!empty($meta['total'])): ?>
        <p class="result-count">
          <?= e(toPersianDigits((string) $meta['total'])) ?> نوشته منتشر شده است.
        </p>
      <?php endif; ?>
    </header>
  <?php endif; ?>

  <?php if (empty($posts)): ?>
    <div class="empty-block">
      <div class="empty-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h10"/>
        </svg>
      </div>
      <h2 class="empty-title">هنوز نوشته‌ای منتشر نشده</h2>
      <p class="empty-text">
        به‌زودی محتوای تازه اینجا منتشر می‌شود. اگر مدیر سایت هستید،
        از پیشخوان اولین نوشته خود را بسازید.
      </p>
      <?php if (canAccessAdmin()): ?>
        <a class="btn btn-primary" href="<?= e(siteUrl('admin/#/posts/new')) ?>">نوشتن اولین مطلب</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="post-grid">
      <?php foreach ($posts as $post): ?>
        <?php themePart('post-card', ['post' => $post]); ?>
      <?php endforeach; ?>
    </div>

    <?php renderPagination($meta, (string) view('base_url', siteUrl('blog'))); ?>
  <?php endif; ?>
</div>
<?php
themePart('footer');
