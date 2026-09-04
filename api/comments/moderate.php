<?php
/**
 * POST /api/comments/moderate.php
 * تأیید، رد، ویرایش یا حذف دیدگاه
 *
 * پارامتر action: approve | pending | spam | trash | edit | delete
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('moderate_comments');

$input = getJsonInput();
$action = (string) ($input['action'] ?? $_GET['action'] ?? '');

// عملیات گروهی
$ids = !empty($input['ids'])
    ? array_values(array_filter(array_map('intval', (array) $input['ids']), fn($i) => $i > 0))
    : array_filter([(int) ($input['id'] ?? $_GET['id'] ?? 0)], fn($i) => $i > 0);

if (empty($ids)) {
    jsonError('هیچ دیدگاهی انتخاب نشده است');
}

if (count($ids) > 100) {
    jsonError('حداکثر ۱۰۰ مورد در هر عملیات مجاز است');
}

// ویرایش متن فقط برای یک دیدگاه انجام می‌شود
if ($action === 'edit') {
    if (count($ids) !== 1) {
        jsonError('ویرایش متن فقط برای یک دیدگاه امکان‌پذیر است');
    }

    if (!updateCommentContent($ids[0], (string) ($input['content'] ?? ''))) {
        jsonError('متن دیدگاه باید بین ۳ تا ۳۰۰۰ کاراکتر باشد');
    }

    jsonSuccess(['id' => $ids[0]], 'دیدگاه ویرایش شد');
}

$statusMap = [
    'approve' => 'approved',
    'pending' => 'pending',
    'spam'    => 'spam',
    'trash'   => 'trash',
];

$affected = 0;

if ($action === 'delete') {
    foreach ($ids as $id) {
        $affected += deleteComment($id) ? 1 : 0;
    }

    $message = toPersianDigits((string) $affected) . ' دیدگاه حذف شد';
} elseif (isset($statusMap[$action])) {
    foreach ($ids as $id) {
        $affected += setCommentStatus($id, $statusMap[$action]) ? 1 : 0;
    }

    $message = toPersianDigits((string) $affected) . ' دیدگاه به‌روزرسانی شد';
} else {
    jsonError('عملیات درخواستی نامعتبر است');
}

if ($affected === 0) {
    jsonError('هیچ دیدگاهی تغییر نکرد');
}

jsonSuccess(['affected' => $affected, 'counts' => getCommentCounts()], $message);
