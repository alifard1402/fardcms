<?php
/**
 * صفحه اصلی و فهرست نمونه‌کارها
 *
 * روی صفحه اصلی، تازه‌ترین نمونه‌کارِ دارای تصویر شاخص، تصویر بزرگ بالای
 * صفحه می‌شود. این‌طور مدیر سایت برای عوض کردن آن فقط کافی است نمونه‌کار
 * تازه منتشر کند و نیازی به تنظیم جداگانه ندارد.
 */

$posts = (array) view('posts', []);
$meta = (array) view('meta', []);
$isFront = (bool) view('is_front');
$settings = getSiteSettings();

$hero = null;
$rest = $posts;

if ($isFront) {
    foreach ($posts as $index => $post) {
        if (postThumbnail($post) !== '') {
            $hero = $post;
            $rest = $posts;
            unset($rest[$index]);
            $rest = array_values($rest);
            break;
        }
    }
}

setView(['has_hero' => $hero !== null]);
themePart('header');
?>

<?php if ($hero !== null): ?>
  <section class="hero" aria-labelledby="hero-title">
    <img class="hero-image" src="<?= e(postThumbnail($hero)) ?>"
         alt="" fetchpriority="high" decoding="async">
    <div class="hero-veil" aria-hidden="true"></div>

    <div class="container hero-content">
      <h1 class="hero-title" id="hero-title"><?= e($settings['site_title']) ?></h1>

      <?php if (!empty($settings['site_tagline'])): ?>
        <p class="hero-text"><?= e($settings['site_tagline']) ?></p>
      <?php endif; ?>

      <div class="hero-actions">
        <a class="btn btn-primary" href="#works">دیدن نمونه‌کارها</a>
        <a class="btn btn-outline" href="<?= e((string) ($hero['url'] ?? routeUrl('blog/' . $hero['slug']))) ?>">
          تازه‌ترین کار: <?= e($hero['title']) ?>
        </a>
      </div>
    </div>
  </section>
<?php elseif ($isFront): ?>
  <section class="hero hero--plain">
    <div class="container hero-content">
      <h1 class="hero-title"><?= e($settings['site_title']) ?></h1>
      <?php if (!empty($settings['site_tagline'])): ?>
        <p class="hero-text"><?= e($settings['site_tagline']) ?></p>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<div class="container">
  <?php if (!$isFront): ?>
    <header class="section-head">
      <h1 class="section-title">نمونه‌کارها</h1>
      <?php if (!empty($meta['total'])): ?>
        <p class="section-sub">
          <?= e(toPersianDigits((string) $meta['total'])) ?> نمونه‌کار منتشر شده است.
        </p>
      <?php endif; ?>
    </header>
  <?php endif; ?>

  <?php
  // نوار دسته‌ها فقط وقتی معنا دارد که واقعاً دسته‌بندی شده باشد
  $categories = getTerms('category', ['hide_empty' => true, 'orderby' => 'count', 'order' => 'desc']);
  ?>
  <?php if (!empty($categories)): ?>
    <nav class="chip-row" aria-label="دسته‌بندی نمونه‌کارها">
      <?php foreach (array_slice($categories, 0, 10) as $category): ?>
        <a class="term-chip" href="<?= e(routeUrl('category/' . $category['slug'])) ?>">
          <?= e($category['name']) ?>
          <span class="chip-count"><?= e(toPersianDigits((string) $category['count'])) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php if (empty($rest) && $hero === null): ?>
    <div class="empty-block">
      <h2 class="empty-title">هنوز نمونه‌کاری منتشر نشده</h2>
      <p class="empty-text">
        اولین مجموعه عکس را از پیشخوان اضافه کنید؛ تصویر شاخص و گالری آن
        همین‌جا نمایش داده می‌شود.
      </p>
      <?php if (canAccessAdmin()): ?>
        <a class="btn btn-primary" href="<?= e(assetUrl('admin/#/posts/new')) ?>">افزودن نمونه‌کار</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php if ($isFront): ?>
      <header class="section-head" id="works">
        <h2 class="section-title">نمونه‌کارها</h2>
      </header>
    <?php endif; ?>

    <div class="work-grid">
      <?php foreach ($rest as $post): ?>
        <?php themePart('post-card', ['post' => $post]); ?>
      <?php endforeach; ?>
    </div>

    <?php renderPagination($meta, (string) view('base_url', routeUrl('blog'))); ?>
  <?php endif; ?>
</div>
<?php
themePart('footer');
