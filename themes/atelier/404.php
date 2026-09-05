<?php
/**
 * صفحه یافت نشد
 */

themePart('header');
?>
<div class="container centered-page">
  <div>
    <div class="big-code">۴۰۴</div>
    <h1 class="empty-title">این صفحه پیدا نشد</h1>
    <p class="empty-text">
      نشانی‌ای که دنبال می‌کردید وجود ندارد یا جابه‌جا شده است.
    </p>

    <form class="search-form" action="<?= e(routeUrl('search')) ?>" method="get" role="search">
      <input class="search-input" type="search" name="q" placeholder="جستجو در سایت…"
             aria-label="عبارت جستجو">
      <button class="btn btn-primary" type="submit">جستجو</button>
    </form>

    <div class="chip-row" style="justify-content:center">
      <a class="btn btn-primary" href="<?= e(routeUrl('')) ?>">بازگشت به خانه</a>
      <a class="btn btn-outline" href="<?= e(routeUrl('blog')) ?>">نمونه‌کارها</a>
    </div>
  </div>
</div>
<?php
themePart('footer');
