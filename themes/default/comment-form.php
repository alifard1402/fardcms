<?php
/**
 * فرم ارسال دیدگاه
 *
 * فرم با fetch به API ارسال می‌شود؛ بدون جاوااسکریپت هم قابل استفاده
 * نگه داشته شده تا خطای شبکه پیام روشنی نشان دهد.
 *
 * @var array<string, mixed> $post
 */

$currentUser = getCurrentUser();
?>
<div class="comment-form-wrap" id="comment-form-wrap">
  <h3 class="footer-heading" style="font-size:16px;margin-bottom:16px">دیدگاه خود را بنویسید</h3>

  <div class="alert alert-success" id="comment-success" style="display:none" role="status">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M20 6L9 17l-5-5"/>
    </svg>
    <span id="comment-success-text"></span>
  </div>

  <div class="alert alert-error" id="comment-error" style="display:none" role="alert">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>
    </svg>
    <span id="comment-error-text"></span>
  </div>

  <div class="reply-notice" id="reply-notice" style="display:none">
    <span>در پاسخ به <strong id="reply-author"></strong></span>
    <button type="button" id="cancel-reply">لغو پاسخ</button>
  </div>

  <?php /* آدرس کامل لازم است: مسیر نسبی روی نشانی‌هایی مثل /blog/slug اشتباه حل می‌شود */ ?>
  <form id="comment-form" novalidate
        data-action="<?= e(assetUrl('api/comments/create.php')) ?>">
    <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
    <input type="hidden" name="parent_id" id="parent-id" value="">

    <?php if ($currentUser === null): ?>
      <div class="form-row">
        <div class="form-field">
          <label class="form-label" for="author_name">نام <span class="required">*</span></label>
          <input class="form-control" type="text" id="author_name" name="author_name"
                 maxlength="100" required autocomplete="name">
        </div>

        <div class="form-field">
          <label class="form-label" for="author_email">ایمیل <span class="required">*</span></label>
          <input class="form-control" type="email" id="author_email" name="author_email"
                 maxlength="255" required autocomplete="email" dir="ltr">
        </div>
      </div>
      <p class="form-note" style="margin-top:-8px;margin-bottom:17px">
        ایمیل شما منتشر نمی‌شود و فقط برای شناسایی دیدگاه استفاده می‌شود.
      </p>
    <?php else: ?>
      <div class="alert alert-info">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 016-6h4a6 6 0 016 6v1"/>
        </svg>
        <span>با نام <strong><?= e($currentUser['name']) ?></strong> دیدگاه می‌گذارید.</span>
      </div>
    <?php endif; ?>

    <div class="form-field">
      <label class="form-label" for="comment-content">دیدگاه <span class="required">*</span></label>
      <textarea class="form-control" id="comment-content" name="content" rows="5"
                maxlength="3000" required
                placeholder="نظر خود را درباره این نوشته بنویسید…"></textarea>
      <p class="form-note">
        <span id="comment-counter">۰</span> از ۳٬۰۰۰ کاراکتر
        <?php if (getOption('moderate_comments', true) && !currentUserCan('moderate_comments')): ?>
          — دیدگاه شما پس از تأیید مدیر نمایش داده می‌شود.
        <?php endif; ?>
      </p>
    </div>

    <button class="btn btn-primary" type="submit" id="comment-submit">
      <span id="comment-submit-text">ارسال دیدگاه</span>
    </button>
  </form>
</div>
