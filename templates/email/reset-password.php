<?php
/**
 * قالب ایمیل بازیابی رمز عبور
 *
 * @var string $name
 * @var string $reset_url
 */

$emailTitle = 'بازیابی رمز عبور';

ob_start();
?>
<p style="margin:0 0 16px;">سلام <strong><?= e($name) ?></strong>،</p>

<p style="margin:0 0 16px;">
  درخواستی برای بازیابی رمز عبور حساب شما دریافت کردیم.
  برای تعیین رمز عبور جدید روی دکمه زیر کلیک کنید:
</p>

<p style="margin:0 0 24px;text-align:center;">
  <a href="<?= e($reset_url) ?>"
     style="display:inline-block;padding:13px 34px;background:#7850ff;color:#ffffff;
            text-decoration:none;border-radius:10px;font-weight:bold;font-size:14px;">
    تعیین رمز عبور جدید
  </a>
</p>

<p style="margin:0 0 16px;color:#6b6b7b;font-size:13px;">
  این لینک تا <strong>یک ساعت</strong> اعتبار دارد و فقط یک بار قابل استفاده است.
</p>

<p style="margin:0 0 8px;color:#6b6b7b;font-size:13px;">
  اگر شما این درخواست را نداده‌اید، این ایمیل را نادیده بگیرید؛ رمز عبور شما تغییر نمی‌کند.
</p>

<p style="margin:16px 0 0;padding-top:16px;border-top:1px solid #eeeef4;color:#9a9aaa;font-size:11px;
          word-break:break-all;direction:ltr;text-align:left;">
  <?= e($reset_url) ?>
</p>
<?php
$emailBody = (string) ob_get_clean();

require __DIR__ . '/layout.php';
