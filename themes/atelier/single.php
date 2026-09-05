<?php
/**
 * یک نمونه‌کار
 *
 * ترتیب نمایش عمدی است: اول ویدیو (اگر باشد)، بعد متن، بعد گالری. در یک
 * آتلیه، متن توضیح کوتاهی است و عکس‌ها حرف اصلی را می‌زنند؛ پس گالری
 * پایین‌ترین چیزی است که کاربر با اسکرول به آن می‌رسد و بیشترین جا را
 * می‌گیرد.
 */

$post = (array) view('post');
$postId = (int) $post['id'];
$comments = (array) view('comments', []);
$commentCount = (int) view('comment_count', 0);
$related = (array) view('related', []);
$prev = view('prev_post');
$next = view('next_post');
$commentsOpen = $post['comment_status'] === 'open' && getOption('allow_comments', true);

$meta = getAllPostMeta($postId);
$subtitle = (string) ($meta['subtitle'] ?? '');
$location = (string) ($meta['shoot_location'] ?? '');
$gallery = postGallery($postId);
$video = postVideo($postId);
$cover = postThumbnail($post);

setView(['has_hero' => $cover !== '']);
themePart('header');
?>

<?php if ($cover !== ''): ?>
  <section class="hero hero--single">
    <img class="hero-image" src="<?= e($cover) ?>" alt="" fetchpriority="high" decoding="async">
    <div class="hero-veil" aria-hidden="true"></div>

    <div class="container hero-content">
      <?php if (!empty($post['categories'])): ?>
        <div class="work-terms">
          <?php foreach ($post['categories'] as $category): ?>
            <a class="term-chip term-chip--light" href="<?= e(routeUrl('category/' . $category['slug'])) ?>">
              <?= e($category['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h1 class="hero-title"><?= e($post['title']) ?></h1>
      <?php if ($subtitle !== ''): ?>
        <p class="hero-text"><?= e($subtitle) ?></p>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<div class="container">
  <article class="single">
    <?php if ($cover === ''): ?>
      <header class="section-head">
        <h1 class="section-title"><?= e($post['title']) ?></h1>
        <?php if ($subtitle !== ''): ?>
          <p class="section-sub"><?= e($subtitle) ?></p>
        <?php endif; ?>
      </header>
    <?php endif; ?>

    <div class="single-facts">
      <span class="fact">
        <time datetime="<?= e(date('c', strtotime($post['published_at'] ?? $post['created_at']))) ?>">
          <?= e($post['date_jalali']) ?>
        </time>
      </span>

      <?php if ($location !== ''): ?>
        <span class="fact"><?= e($location) ?></span>
      <?php endif; ?>

      <?php if (!empty($gallery)): ?>
        <span class="fact"><?= e(toPersianDigits((string) count($gallery))) ?> تصویر</span>
      <?php endif; ?>
    </div>

    <?php if ($video !== null): ?>
      <div class="video-wrap">
        <?php if ($video['type'] === 'aparat'): ?>
          <?php /* نشانی را خود سیستم از شناسه می‌سازد؛ ورودی کاربر هرگز HTML نمی‌شود */ ?>
          <iframe src="<?= e($video['embed']) ?>" title="<?= e($post['title']) ?>"
                  allow="autoplay; fullscreen; picture-in-picture"
                  allowfullscreen loading="lazy" referrerpolicy="no-referrer"></iframe>
        <?php else: ?>
          <video controls preload="metadata"
                 <?= $video['poster'] !== '' ? 'poster="' . e($video['poster']) . '"' : '' ?>>
            <source src="<?= e($video['src']) ?>" type="video/mp4">
            مرورگر شما پخش ویدیو را پشتیبانی نمی‌کند.
          </video>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (trim((string) $post['content']) !== ''): ?>
      <div class="prose">
        <?= $post['content'] /* در ذخیره‌سازی پاک‌سازی شده است */ ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($gallery)): ?>
      <section class="gallery" aria-label="گالری تصاویر">
        <?php foreach ($gallery as $index => $image): ?>
          <a class="gallery-item" href="<?= e($image['url']) ?>"
             data-lightbox
             data-caption="<?= e((string) ($image['alt_text'] ?: $post['title'])) ?>">
            <img src="<?= e($image['thumbnail_url']) ?>"
                 alt="<?= e((string) ($image['alt_text'] ?: '')) ?>"
                 <?= $image['width'] ? 'width="' . (int) $image['width'] . '"' : '' ?>
                 <?= $image['height'] ? 'height="' . (int) $image['height'] . '"' : '' ?>
                 loading="<?= $index < 4 ? 'eager' : 'lazy' ?>" decoding="async">
          </a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($post['tags'])): ?>
      <div class="chip-row">
        <?php foreach ($post['tags'] as $tag): ?>
          <a class="term-chip" href="<?= e(routeUrl('tag/' . $tag['slug'])) ?>">#<?= e($tag['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <?php if ($prev || $next): ?>
    <nav class="post-nav" aria-label="پیمایش نمونه‌کارها">
      <?php if ($prev): ?>
        <a class="post-nav-link" href="<?= e(routeUrl('blog/' . $prev['slug'])) ?>" rel="prev">
          <span class="post-nav-label">کار قبلی</span>
          <span class="post-nav-title"><?= e($prev['title']) ?></span>
        </a>
      <?php else: ?><span></span><?php endif; ?>

      <?php if ($next): ?>
        <a class="post-nav-link post-nav-link--end" href="<?= e(routeUrl('blog/' . $next['slug'])) ?>" rel="next">
          <span class="post-nav-label">کار بعدی</span>
          <span class="post-nav-title"><?= e($next['title']) ?></span>
        </a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>

  <?php if (!empty($related)): ?>
    <section class="section">
      <header class="section-head">
        <h2 class="section-title">کارهای مرتبط</h2>
      </header>
      <div class="work-grid">
        <?php foreach ($related as $item): ?>
          <?php themePart('post-card', ['post' => $item]); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($commentsOpen || $commentCount > 0): ?>
    <section class="section comments-section" id="comments">
      <header class="section-head">
        <h2 class="section-title">
          دیدگاه‌ها
          <?php if ($commentCount > 0): ?>
            <span class="section-count"><?= e(toPersianDigits((string) $commentCount)) ?></span>
          <?php endif; ?>
        </h2>
      </header>

      <?php if (!empty($comments)): ?>
        <ul class="comment-list"><?php renderComments($comments); ?></ul>
      <?php elseif ($commentsOpen): ?>
        <p class="muted">اولین دیدگاه را شما بنویسید.</p>
      <?php endif; ?>

      <?php if ($commentsOpen): ?>
        <?php themePart('comment-form', ['post' => $post]); ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<?php
themePart('footer');
