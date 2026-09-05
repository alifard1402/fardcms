<?php
/**
 * آرشیو دسته، برچسب و نویسنده
 */

themePart('header');

$posts = (array) view('posts', []);
$meta = (array) view('meta', []);
?>
<div class="container">
  <header class="section-head">
    <h1 class="section-title"><?= e((string) view('archive_title', 'آرشیو')) ?></h1>

    <?php if (view('archive_description')): ?>
      <p class="section-sub"><?= e((string) view('archive_description')) ?></p>
    <?php elseif (!empty($meta['total'])): ?>
      <p class="section-sub">
        <?= e(toPersianDigits((string) $meta['total'])) ?> نمونه‌کار در این بخش
      </p>
    <?php endif; ?>
  </header>

  <?php if (empty($posts)): ?>
    <div class="empty-block">
      <h2 class="empty-title">چیزی در این بخش نیست</h2>
      <p class="empty-text">هنوز نمونه‌کاری در این دسته منتشر نشده است.</p>
      <a class="btn btn-primary" href="<?= e(routeUrl('')) ?>">بازگشت به خانه</a>
    </div>
  <?php else: ?>
    <div class="work-grid">
      <?php foreach ($posts as $post): ?>
        <?php themePart('post-card', ['post' => $post]); ?>
      <?php endforeach; ?>
    </div>

    <?php renderPagination($meta, (string) view('base_url', routeUrl('blog'))); ?>
  <?php endif; ?>
</div>
<?php
themePart('footer');
