<?php
/**
 * قالب ایمیل اطلاع‌رسانی دیدگاه جدید
 *
 * @var string $author_name
 * @var string $content
 * @var string $post_title
 * @var string $moderate_url
 */

$emailTitle = 'دیدگاه جدید';

ob_start();
?>
<p style="margin:0 0 16px;">یک دیدگاه جدید در انتظار بررسی است.</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="background:#fafafc;border:1px solid #eeeef4;border-radius:10px;margin-bottom:20px;">
  <tr>
    <td style="padding:16px;">
      <div style="color:#8a8a9a;font-size:12px;margin-bottom:6px;">نوشته</div>
      <div style="font-weight:bold;margin-bottom:14px;"><?= e($post_title) ?></div>

      <div style="color:#8a8a9a;font-size:12px;margin-bottom:6px;">نویسنده</div>
      <div style="margin-bottom:14px;"><?= e($author_name) ?></div>

      <div style="color:#8a8a9a;font-size:12px;margin-bottom:6px;">متن دیدگاه</div>
      <div style="line-height:1.9;"><?= nl2br(e($content)) ?></div>
    </td>
  </tr>
</table>

<p style="margin:0;text-align:center;">
  <a href="<?= e($moderate_url) ?>"
     style="display:inline-block;padding:12px 30px;background:#7850ff;color:#ffffff;
            text-decoration:none;border-radius:10px;font-weight:bold;font-size:14px;">
    بررسی دیدگاه‌ها
  </a>
</p>
<?php
$emailBody = (string) ob_get_clean();

require __DIR__ . '/layout.php';
