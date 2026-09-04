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
  <header class="page-header">
    <h1 class="page-heading"><?= e((string) view('archive_title')) ?></h1>

    <?php if ($query !== ''): ?>
      <p class="result-count">
        <?php if (!empty($meta['total'])): ?>
          <?= e(toPersianDigits((string) $meta['total'])) ?> نتیجه یافت شد.
        <?php else: ?>
          نتیجه‌ای یافت نشد.
        <?php endif; ?>
      </p>
    <?php endif; ?>
  </header>

  <form class="search-form" action="<?= e(routeUrl('search')) ?>" method="get" role="search"
        style="max-width:560px;margin-bottom:38px">
    <input class="search-input" type="search" name="q" value="<?= e($query) ?>"
           placeholder="چه چیزی را جستجو می‌کنید؟" aria-label="عبارت جستجو"
           <?= $query === '' ? 'autofocus' : '' ?>>
    <button class="btn btn-primary" type="submit">جستجو</button>
  </form>

  <?php if ($query === ''): ?>
    <div class="empty-block">
      <div class="empty-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
        </svg>
      </div>
      <h2 class="empty-title">عبارتی برای جستجو وارد کنید</h2>
      <p class="empty-text">عنوان، خلاصه و متن نوشته‌ها جستجو می‌شود.</p>
    </div>
  <?php elseif (empty($posts)): ?>
    <div class="empty-block">
      <div class="empty-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
        </svg>
      </div>
      <h2 class="empty-title">نتیجه‌ای برای «<?= e($query) ?>» پیدا نشد</h2>
      <p class="empty-text">
        املای عبارت را بررسی کنید یا با کلمات کلیدی کوتاه‌تر جستجو کنید.
      </p>
      <a class="btn btn-outline" href="<?= e(routeUrl('blog')) ?>">مشاهده همه نوشته‌ها</a>
    </div>
  <?php else: ?>
    <div class="post-grid">
      <?php foreach ($posts as $post): ?>
        <?php themePart('post-card', ['post' => $post]); ?>
      <?php endforeach; ?>
    </div>

    <?php renderPagination($meta, (string) view('base_url')); ?>
  <?php endif; ?>
</div>
<?php
themePart('footer');
