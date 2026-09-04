<?php
/**
 * نمایش یک برگه
 */

themePart('header');

$post = (array) view('post');
$children = (array) view('children', []);
$comments = (array) view('comments', []);
$commentCount = (int) view('comment_count', 0);
$commentsOpen = $post['comment_status'] === 'open' && getOption('allow_comments', true);
?>
<div class="container container--narrow">
  <article class="single-article">
    <header class="single-header">
      <h1 class="single-title"><?= e($post['title']) ?></h1>

      <?php if (!view('is_front')): ?>
        <div class="single-meta">
          <span class="meta-item">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>
            </svg>
            آخرین به‌روزرسانی: <?= e(jalaliDate('j F Y', $post['updated_at'])) ?>
          </span>
        </div>
      <?php endif; ?>
    </header>

    <?php if (!empty($post['featured_image'])): ?>
      <figure class="single-featured">
        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>"
             width="1200" height="675">
      </figure>
    <?php endif; ?>

    <?php /* محتوا در زمان ذخیره پاک‌سازی شده است */ ?>
    <div class="content"><?= $post['content'] ?></div>
  </article>

  <?php if (!empty($children)): ?>
    <section class="section" aria-labelledby="children-heading">
      <h2 class="section-title" id="children-heading">در این بخش</h2>
      <div class="child-pages">
        <?php foreach ($children as $child): ?>
          <a class="child-page-link" href="<?= e($child['url']) ?>">
            <span class="child-page-title"><?= e($child['title']) ?></span>
            <?php if (!empty($child['excerpt'])): ?>
              <span class="child-page-excerpt"><?= e(makeExcerpt((string) $child['excerpt'], 90)) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php if ($commentsOpen || !empty($comments)): ?>
    <section class="section" id="comments" aria-labelledby="comments-heading">
      <h2 class="section-title" id="comments-heading">
        <?php if ($commentCount > 0): ?>
          <?= e(toPersianDigits((string) $commentCount)) ?> دیدگاه
        <?php else: ?>
          دیدگاه‌ها
        <?php endif; ?>
      </h2>

      <?php if (!empty($comments)): ?>
        <ul class="comment-list">
          <?php renderComments($comments); ?>
        </ul>
      <?php endif; ?>

      <?php if ($commentsOpen): ?>
        <?php themePart('comment-form', ['post' => $post]); ?>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
<?php
themePart('footer');
