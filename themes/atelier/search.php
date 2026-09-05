<?php
/**
 * نتایج جستجو
 */

themePart('header');

$posts = (array) view('posts', []);
$meta = (array) view('meta', []);
$query = (string) view('query', '');
?>
<div class="container">
  <header class="section-head">
    <h1 class="section-title">جستجو</h1>
    <form class="search-form" action="<?= e(routeUrl('search')) ?>" method="get" role="search">
      <input class="search-input" type="search" name="q" value="<?= e($query) ?>"
             placeholder="نام مجموعه، دسته یا موضوع…" aria-label="عبارت جستجو" autofocus>
      <button class="btn btn-primary" type="submit">جستجو</button>
    </form>

    <?php if ($query !== ''): ?>
      <p class="section-sub">
        <?= e(toPersianDigits((string) ($meta['total'] ?? 0))) ?> نتیجه برای «<?= e($query) ?>»
      </p>
    <?php endif; ?>
  </header>

  <?php if ($query !== '' && empty($posts)): ?>
    <div class="empty-block">
      <h2 class="empty-title">نتیجه‌ای پیدا نشد</h2>
      <p class="empty-text">عبارت دیگری را امتحان کنید یا از فهرست دسته‌ها استفاده کنید.</p>
    </div>
  <?php elseif (!empty($posts)): ?>
    <div class="work-grid">
      <?php foreach ($posts as $post): ?>
        <?php themePart('post-card', ['post' => $post]); ?>
      <?php endforeach; ?>
    </div>

    <?php renderPagination($meta, routeUrl('search') . '?q=' . urlencode($query)); ?>
  <?php endif; ?>
</div>
<?php
themePart('footer');
