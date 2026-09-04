<?php
/**
 * چیدمان مشترک ایمیل‌ها
 *
 * متغیرهای موردنیاز: $emailTitle، $emailBody
 * از آنجا که کلاینت‌های ایمیل از CSS خارجی پشتیبانی نمی‌کنند،
 * تمام استایل‌ها به صورت inline نوشته شده‌اند.
 *
 * @var string $emailTitle
 * @var string $emailBody
 */

$siteTitle = (string) getOption('site_title', SITE_NAME);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title><?= e($emailTitle) ?></title>
</head>
<body style="margin:0;padding:24px;background:#f4f4f7;font-family:Tahoma,Arial,sans-serif;direction:rtl;">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
         style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;
                box-shadow:0 2px 8px rgba(0,0,0,.06);">
    <tr>
      <td style="padding:28px 32px;background:linear-gradient(135deg,#7850ff,#ff6496);text-align:center;">
        <div style="font-size:20px;font-weight:bold;color:#ffffff;"><?= e($siteTitle) ?></div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px;color:#2b2b35;font-size:14px;line-height:2;">
        <?= $emailBody ?>
      </td>
    </tr>
    <tr>
      <td style="padding:18px 32px;background:#fafafc;border-top:1px solid #eeeef4;
                 text-align:center;color:#8a8a9a;font-size:12px;line-height:1.9;">
        این ایمیل به‌صورت خودکار ارسال شده است؛ لطفاً به آن پاسخ ندهید.<br>
        <a href="<?= e(siteUrl('')) ?>" style="color:#7850ff;text-decoration:none;"><?= e($siteTitle) ?></a>
      </td>
    </tr>
  </table>
</body>
</html>
