<?php
/**
 * آرشیو دسته، برچسب و نویسنده
 */

themePart('header');

$posts = (array) view('posts', []);
$meta = (array) view('meta', []);
$author = view('author');
?>
<div class="container">
  <header class="page-header">
    <h1 class="page-heading"><?= e((string) view('archive_title')) ?></h1>

    <?php if (view('archive_subtitle')): ?>
      <p class="page-subheading"><?= e((string) view('archive_subtitle')) ?></p>
    <?php endif; ?>

    <p class="result-count">
      <?php if (!empty($meta['total'])): ?>
        <?= e(toPersianDigits((string) $meta['total'])) ?> نوشته یافت شد.
      <?php else: ?>
        نوشته‌ای در این بخش وجود ندارد.
      <?php endif; ?>
    </p>
  </header>

  <?php if ($author !== null && !empty($author['bio'])): ?>
    <div class="author-box" style="margin-bottom:34px;margin-top:0">
      <?php if (!empty($author['avatar'])): ?>
        <img class="author-avatar" src="<?= e($author['avatar']) ?>" alt="" width="58" height="58">
      <?php else: ?>
        <span class="author-avatar author-avatar--initial">
          <?= e(mb_substr((string) $author['name'], 0, 1, 'UTF-8')) ?>
        </span>
      <?php endif; ?>
      <div>
        <div class="author-box-name"><?= e($author['name']) ?></div>
        <p class="author-box-bio"><?= e($author['bio']) ?></p>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($posts)): ?>
    <div class="empty-block">
      <div class="empty-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.6 13.4l-7.2 7.2a2 2 0 01-2.8 0l-7.2-7.2A2 2 0 013 12V5a2 2 0 012-2h7a2 2 0 011.4.6l7.2 7.2a2 2 0 010 2.6z"/>
          <circle cx="7.5" cy="7.5" r="1.2"/>
        </svg>
      </div>
      <h2 class="empty-title">نوشته‌ای یافت نشد</h2>
      <p class="empty-text">در این بخش هنوز نوشته‌ای منتشر نشده است.</p>
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
