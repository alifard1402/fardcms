<?php
/**
 * POST /api/comments/create.php
 * ثبت دیدگاه جدید توسط بازدیدکننده (عمومی)
 */

require_once __DIR__ . '/../bootstrap.php';
require_once TEMPLATES_PATH . '/email/mailer.php';

apiBootstrap(['POST']);

// محدودیت سخت‌گیرانه برای جلوگیری از هرزنامه
apiRateLimit(10, 600, 'comment_submit');

$input = getJsonInput();
$result = addComment($input);

if (!$result['success']) {
    jsonError($result['message']);
}

jsonSuccess($result['data'], $result['message']);
