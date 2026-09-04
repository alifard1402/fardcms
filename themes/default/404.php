<?php
/**
 * صفحه یافت نشد
 */

themePart('header');
?>
<div class="container centered-page">
  <div>
    <div class="big-code">۴۰۴</div>
    <h1 class="empty-title" style="font-size:24px">این صفحه پیدا نشد</h1>
    <p class="empty-text">
      نشانی‌ای که دنبال می‌کردید وجود ندارد یا جابه‌جا شده است.
      می‌توانید از فهرست سایت یا جستجو استفاده کنید.
    </p>

    <form class="search-form" action="<?= e(siteUrl('search')) ?>" method="get" role="search"
          style="max-width:420px;margin:0 auto 22px">
      <input class="search-input" type="search" name="q" placeholder="جستجو در سایت…"
             aria-label="عبارت جستجو">
      <button class="btn btn-primary" type="submit">جستجو</button>
    </form>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-primary" href="<?= e(siteUrl('')) ?>">بازگشت به خانه</a>
      <a class="btn btn-outline" href="<?= e(siteUrl('blog')) ?>">مشاهده وبلاگ</a>
    </div>
  </div>
</div>
<?php
themePart('footer');
