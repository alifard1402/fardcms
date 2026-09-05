<?php
/**
 * Middleware — امنیت، احراز هویت و کنترل دسترسی درخواست‌ها
 */

/**
 * هدرهای امنیتی برای تمام پاسخ‌ها
 */
function securityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header_remove('X-Powered-By');
}

/**
 * راه‌اندازی استاندارد یک نقطه پایانی API
 *
 * ترتیب کارها: هدرهای امنیتی، بررسی روش، محدودیت نرخ و بررسی CSRF.
 *
 * @param string[] $methods روش‌های مجاز HTTP
 * @return string روش درخواست فعلی
 */
function apiBootstrap(array $methods = ['GET']): string
{
    securityHeaders();
    header('Content-Type: application/json; charset=utf-8');

    $method = allowMethods($methods);

    apiRateLimit();

    // درخواست‌های تغییردهنده باید توکن CSRF معتبر داشته باشند
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        requireCsrf();
    }

    return $method;
}

/**
 * بررسی توکن CSRF برای درخواست‌های تغییردهنده
 *
 * پنل مدیریت یک SPA است و با کوکی نشست کار می‌کند، بنابراین بدون این
 * بررسی در معرض حمله CSRF قرار می‌گیرد.
 */
function requireCsrf(): void
{
    // کاربر مهمان نشستی برای محافظت ندارد (مثلاً ورود و ثبت‌نام)
    if (empty($_SESSION['csrf_token'])) {
        return;
    }

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if ($token === '') {
        $input = getJsonInput();
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    }

    if (!validateCsrfToken(is_string($token) ? $token : '')) {
        jsonError('توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.', 419);
    }
}

/**
 * بررسی ورود کاربر — اگر وارد نشده باشد خطا یا ریدایرکت
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            jsonError('لطفاً ابتدا وارد شوید', 401);
        }

        header('Location: ' . siteUrl('login.php'));
        exit;
    }
}

/**
 * بررسی دسترسی مدیر کل
 */
function requireAdmin(): void
{
    requireAuth();

    if (!isAdmin()) {
        denyAccess();
    }
}

/**
 * بررسی یک دسترسی مشخص (capability)
 */
function requireCap(string $capability): void
{
    requireAuth();

    if (!currentUserCan($capability)) {
        denyAccess();
    }
}

/**
 * پاسخ عدم دسترسی
 */
function denyAccess(): void
{
    if (isApiRequest()) {
        jsonError('شما دسترسی لازم برای این عملیات را ندارید', 403);
    }

    http_response_code(403);
    header('Location: ' . siteUrl('login.php'));
    exit;
}

/**
 * آیا درخواست از نوع API است؟
 */
function isApiRequest(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    return str_contains($accept, 'application/json')
        || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
        || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
}

/**
 * محدودیت نرخ درخواست برای API
 *
 * هر $scope سطل شمارش مستقل خود را دارد؛ بنابراین محدودیت سخت‌گیرانه یک
 * نقطه پایانی (مثلاً ارسال دیدگاه) با محدودیت عمومی درخواست‌ها قاطی نمی‌شود.
 */
function apiRateLimit(int $maxRequests = 240, int $window = 60, string $scope = 'global'): void
{
    $key = hash('sha256', $scope . '|' . getClientIp() . '|' . currentUserId() . SECRET_KEY);
    $file = sys_get_temp_dir() . '/fardcms_api_rl_' . $key . '.json';

    $timestamps = file_exists($file)
        ? (json_decode((string) file_get_contents($file), true) ?: [])
        : [];

    $windowStart = time() - $window;
    $recent = array_values(array_filter($timestamps, fn($t) => $t > $windowStart));

    if (count($recent) >= $maxRequests) {
        header('Retry-After: ' . $window);
        jsonError('تعداد درخواست‌ها بیش از حد مجاز است. لطفاً کمی صبر کنید.', 429);
    }

    $recent[] = time();
    file_put_contents($file, json_encode($recent), LOCK_EX);
}
