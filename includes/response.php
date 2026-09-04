<?php
/**
 * توابع پاسخ JSON استاندارد
 */

function jsonResponse(bool $success, string $message = '', array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => $success];

    if ($message !== '') {
        $response['message'] = $message;
    }

    if (!empty($data)) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * پاسخ موفق همراه با داده
 */
function jsonSuccess(array $data = [], string $message = ''): void
{
    jsonResponse(true, $message, $data);
}

/**
 * پاسخ خطا
 */
function jsonError(string $message, int $code = 400, array $data = []): void
{
    jsonResponse(false, $message, $data, $code);
}

/**
 * دریافت بدنه درخواست JSON
 */
function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}

/**
 * فقط اجازه دادن به روش‌های مشخص HTTP
 *
 * @param string[] $methods
 */
function allowMethods(array $methods): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // پاسخ به درخواست preflight
    if ($method === 'OPTIONS') {
        header('Allow: ' . implode(', ', $methods));
        http_response_code(204);
        exit;
    }

    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        jsonError('روش درخواست نامعتبر است', 405);
    }

    return $method;
}

/**
 * خروجی استاندارد صفحه‌بندی
 */
function paginationMeta(int $total, int $page, int $perPage): array
{
    return [
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
    ];
}
