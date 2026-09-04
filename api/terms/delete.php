<?php
/**
 * POST|DELETE /api/terms/delete.php
 * حذف یک دسته یا برچسب
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['POST', 'DELETE']);
requireCap('manage_categories');

$input = getJsonInput();
$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);

if ($id <= 0) {
    jsonError('شناسه نامعتبر است');
}

if (getTerm($id) === null) {
    jsonError('مورد درخواستی یافت نشد', 404);
}

if (!deleteTerm($id)) {
    jsonError('حذف ممکن نشد');
}

recountAllTerms();

jsonSuccess(['id' => $id], 'با موفقیت حذف شد');
