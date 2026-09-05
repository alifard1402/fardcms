<?php
/**
 * برگه (درباره من، تعرفه‌ها، تماس و ...)
 */

$post = (array) view('post');
$children = (array) view('children', []);
$comments = (array) view('comments', []);
$commentCount = (int) view('comment_count', 0);
$commentsOpen = $post['comment_status'] === 'open' && getOption('allow_comments', true);
$cover = postThumbnail($post);
$gallery = postGallery((int) $post['id']);

setView(['has_hero' => $cover !== '']);
themePart('header');
?>

<?php if ($cover !== ''): ?>
  <section class="hero hero--single">
    <img class="hero-image" src="<?= e($cover) ?>" alt="" fetchpriority="high" decoding="async">
    <div class="hero-veil" aria-hidden="true"></div>
    <div class="container hero-content">
      <h1 class="hero-title"><?= e($post['title']) ?></h1>
    </div>
  </section>
<?php endif; ?>

<div class="container">
  <article class="single single--narrow">
    <?php if ($cover === ''): ?>
      <header class="section-head">
        <h1 class="section-title"><?= e($post['title']) ?></h1>
      </header>
    <?php endif; ?>

    <div class="prose">
      <?= $post['content'] /* در ذخیره‌سازی پاک‌سازی شده است */ ?>
    </div>

    <?php if (!empty($gallery)): ?>
      <section class="gallery" aria-label="گالری تصاویر">
        <?php foreach ($gallery as $index => $image): ?>
          <a class="gallery-item" href="<?= e($image['url']) ?>" data-lightbox
             data-caption="<?= e((string) ($image['alt_text'] ?: $post['title'])) ?>">
            <img src="<?= e($image['thumbnail_url']) ?>"
                 alt="<?= e((string) ($image['alt_text'] ?: '')) ?>"
                 loading="<?= $index < 4 ? 'eager' : 'lazy' ?>" decoding="async">
          </a>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($children)): ?>
      <nav class="chip-row" aria-label="زیربرگه‌ها">
        <?php foreach ($children as $child): ?>
          <a class="term-chip" href="<?= e((string) ($child['url'] ?? routeUrl($child['slug']))) ?>"><?= e($child['title']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </article>

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
      <?php endif; ?>

      <?php if ($commentsOpen): ?>
        <?php themePart('comment-form', ['post' => $post]); ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<?php
themePart('footer');
