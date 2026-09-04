<?php
/**
 * POST /api/media/upload.php  (multipart/form-data)
 * آپلود یک یا چند فایل به کتابخانه رسانه
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST']);
requireCap('upload_files');

if (empty($_FILES['file'])) {
    jsonError('فایلی ارسال نشده است');
}

$uploaded = [];
$errors = [];

// پشتیبانی از آپلود تک‌فایلی و چندفایلی (file[])
$files = is_array($_FILES['file']['name'])
    ? normalizeMultiUpload($_FILES['file'])
    : [$_FILES['file']];

if (count($files) > 20) {
    jsonError('حداکثر ۲۰ فایل در هر درخواست مجاز است');
}

foreach ($files as $file) {
    $result = uploadMedia($file);

    if ($result['success']) {
        $uploaded[] = $result['data']['media'];
    } else {
        $errors[] = ($file['name'] ?? 'فایل') . ': ' . $result['message'];
    }
}

if (empty($uploaded)) {
    jsonError(implode(' | ', $errors) ?: 'آپلود ناموفق بود');
}

jsonSuccess(
    ['media' => $uploaded, 'errors' => $errors],
    toPersianDigits((string) count($uploaded)) . ' فایل آپلود شد'
    . (!empty($errors) ? ' — ' . toPersianDigits((string) count($errors)) . ' فایل ناموفق' : '')
);

/**
 * تبدیل ساختار $_FILES چندفایلی به فهرستی از رکوردهای مستقل
 *
 * @param array<string, array<int, mixed>> $field
 * @return array<int, array<string, mixed>>
 */
function normalizeMultiUpload(array $field): array
{
    $files = [];

    foreach (array_keys($field['name']) as $i) {
        $files[] = [
            'name'     => $field['name'][$i],
            'type'     => $field['type'][$i],
            'tmp_name' => $field['tmp_name'][$i],
            'error'    => $field['error'][$i],
            'size'     => $field['size'][$i],
        ];
    }

    return $files;
}
