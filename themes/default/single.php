<?php
/**
 * نمایش یک نوشته
 */

themePart('header');

$post = (array) view('post');
$comments = (array) view('comments', []);
$commentCount = (int) view('comment_count', 0);
$related = (array) view('related', []);
$prev = view('prev_post');
$next = view('next_post');
$commentsOpen = $post['comment_status'] === 'open' && getOption('allow_comments', true);
?>
<div class="container container--narrow">
  <article class="single-article">
    <header class="single-header">
      <?php if (!empty($post['categories'])): ?>
        <div class="post-card-terms">
          <?php foreach ($post['categories'] as $category): ?>
            <a class="term-chip" href="<?= e(siteUrl('category/' . $category['slug'])) ?>">
              <?= e($category['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <h1 class="single-title"><?= e($post['title']) ?></h1>

      <div class="single-meta">
        <?php if (!empty($post['author_name'])): ?>
          <span class="author-chip">
            <?php if (!empty($post['author_avatar'])): ?>
              <img class="author-avatar" src="<?= e($post['author_avatar']) ?>"
                   alt="" width="34" height="34">
            <?php else: ?>
              <span class="author-avatar author-avatar--initial">
                <?= e(mb_substr((string) $post['author_name'], 0, 1, 'UTF-8')) ?>
              </span>
            <?php endif; ?>
            <?= e($post['author_name']) ?>
          </span>
        <?php endif; ?>

        <span class="meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>
          </svg>
          <time datetime="<?= e(date('c', strtotime($post['published_at'] ?? $post['created_at']))) ?>">
            <?= e($post['date_jalali']) ?>
          </time>
        </span>

        <span class="meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
          <?= e(toPersianDigits((string) $post['views'])) ?> بازدید
        </span>

        <?php if ($commentCount > 0): ?>
          <a class="meta-item" href="#comments">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 12a8 8 0 01-8 8H8l-5 3v-4.5A8 8 0 0113 4a8 8 0 018 8z"/>
            </svg>
            <?= e(toPersianDigits((string) $commentCount)) ?> دیدگاه
          </a>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!empty($post['featured_image'])): ?>
      <figure class="single-featured">
        <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>"
             width="1200" height="675">
      </figure>
    <?php endif; ?>

    <?php /* محتوا در زمان ذخیره با sanitizeHtml() پاک‌سازی شده است */ ?>
    <div class="content"><?= $post['content'] ?></div>

    <footer class="single-footer">
      <?php if (!empty($post['tags'])): ?>
        <div class="tag-list">
          <span class="tag-list-label">برچسب‌ها:</span>
          <?php foreach ($post['tags'] as $tag): ?>
            <a class="tag-pill" href="<?= e(siteUrl('tag/' . $tag['slug'])) ?>">
              <?= e($tag['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($post['author_name'])): ?>
        <?php
        // اطلاعات کامل نویسنده برای جعبه معرفی
        $authorStmt = Database::getConnection()->prepare(
            'SELECT name, username, bio, avatar FROM ' . tbl('users') . ' WHERE id = ? LIMIT 1'
        );
        $authorStmt->execute([$post['author_id']]);
        $author = $authorStmt->fetch();
        ?>
        <?php if ($author && !empty($author['bio'])): ?>
          <div class="author-box">
            <?php if (!empty($author['avatar'])): ?>
              <img class="author-avatar" src="<?= e($author['avatar']) ?>" alt="" width="58" height="58">
            <?php else: ?>
              <span class="author-avatar author-avatar--initial">
                <?= e(mb_substr((string) $author['name'], 0, 1, 'UTF-8')) ?>
              </span>
            <?php endif; ?>
            <div>
              <div class="author-box-name">
                <a href="<?= e(siteUrl('author/' . $author['username'])) ?>"><?= e($author['name']) ?></a>
              </div>
              <p class="author-box-bio"><?= e($author['bio']) ?></p>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($prev !== null || $next !== null): ?>
        <nav class="post-nav" aria-label="نوشته‌های قبلی و بعدی">
          <?php if ($prev !== null): ?>
            <a class="post-nav-link" href="<?= e(siteUrl('blog/' . $prev['slug'])) ?>" rel="prev">
              <span class="post-nav-dir">← نوشته قبلی</span>
              <span class="post-nav-title"><?= e($prev['title']) ?></span>
            </a>
          <?php else: ?>
            <span></span>
          <?php endif; ?>

          <?php if ($next !== null): ?>
            <a class="post-nav-link post-nav-link--next" href="<?= e(siteUrl('blog/' . $next['slug'])) ?>" rel="next">
              <span class="post-nav-dir">نوشته بعدی →</span>
              <span class="post-nav-title"><?= e($next['title']) ?></span>
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </footer>
  </article>

  <?php if (!empty($related)): ?>
    <section class="section" aria-labelledby="related-heading">
      <h2 class="section-title" id="related-heading">نوشته‌های مرتبط</h2>
      <div class="post-grid">
        <?php foreach ($related as $item): ?>
          <?php themePart('post-card', ['post' => $item]); ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

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
    <?php elseif ($commentsOpen): ?>
      <p class="text-muted">اولین کسی باشید که دیدگاه می‌گذارد.</p>
    <?php endif; ?>

    <?php if ($commentsOpen): ?>
      <?php themePart('comment-form', ['post' => $post]); ?>
    <?php else: ?>
      <div class="alert alert-info mt-lg">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <span>ارسال دیدگاه برای این نوشته بسته است.</span>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php
themePart('footer');
