<?php
/**
 * ارسال ایمیل
 *
 * از تابع mail() داخلی PHP استفاده می‌شود. در محیط توسعه که سرور ایمیل
 * وجود ندارد، پیام‌ها در storage/mail.log ذخیره می‌شوند تا قابل بررسی باشند.
 */

/**
 * ارسال یک ایمیل HTML
 */
function sendMail(string $to, string $subject, string $htmlBody): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $siteTitle = (string) getOption('site_title', SITE_NAME);
    $fromDomain = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';

    // نام فرستنده باید MIME-encode شود تا فارسی درست نمایش داده شود
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . mb_encode_mimeheader($siteTitle, 'UTF-8') . ' <no-reply@' . $fromDomain . '>',
        'X-Mailer: FardCMS',
    ];

    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');

    // در حالت توسعه ایمیل‌ها فقط ثبت می‌شوند
    if (DEBUG_MODE || !function_exists('mail')) {
        return logMail($to, $subject, $htmlBody);
    }

    $sent = @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));

    if (!$sent) {
        error_log("Failed to send mail to $to — falling back to mail log");
        logMail($to, $subject, $htmlBody);
    }

    return $sent;
}

/**
 * ثبت ایمیل در فایل گزارش (محیط توسعه)
 */
function logMail(string $to, string $subject, string $body): bool
{
    $logFile = STORAGE_PATH . '/mail.log';

    $entry = str_repeat('=', 70) . "\n"
        . 'تاریخ:   ' . date('Y-m-d H:i:s') . "\n"
        . "گیرنده:  $to\n"
        . "موضوع:   $subject\n"
        . str_repeat('-', 70) . "\n"
        . $body . "\n\n";

    return (bool) @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * ارسال ایمیل بازیابی رمز عبور
 */
function sendPasswordResetEmail(string $email, string $name, string $token): bool
{
    $resetUrl = siteUrl('reset-password.html?token=' . urlencode($token));

    $body = renderEmailTemplate('reset-password', [
        'name'      => $name,
        'reset_url' => $resetUrl,
    ]);

    return sendMail($email, 'بازیابی رمز عبور — ' . getOption('site_title', SITE_NAME), $body);
}

/**
 * ارسال اطلاع‌رسانی دیدگاه جدید به مدیر
 */
function sendNewCommentNotification(string $adminEmail, array $comment, array $post): bool
{
    $body = renderEmailTemplate('new-comment', [
        'author_name' => $comment['author_name'],
        'content'     => $comment['content'],
        'post_title'  => $post['title'],
        'moderate_url' => siteUrl('admin/#/comments'),
    ]);

    return sendMail($adminEmail, 'دیدگاه جدید روی «' . $post['title'] . '»', $body);
}

/**
 * رندر یک قالب ایمیل
 *
 * @param array<string, string> $vars متغیرهایی که در قالب در دسترس‌اند
 */
function renderEmailTemplate(string $template, array $vars = []): string
{
    $file = TEMPLATES_PATH . '/email/' . basename($template) . '.php';

    if (!is_file($file)) {
        error_log("Email template not found: $template");

        return '<p>' . e((string) ($vars['content'] ?? '')) . '</p>';
    }

    extract($vars, EXTR_SKIP);

    ob_start();
    require $file;

    return (string) ob_get_clean();
}
