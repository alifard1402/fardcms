<?php
/**
 * توابع احراز هویت و مدیریت Session
 */

// شروع session با تنظیمات امن
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    // اگر سایت روی HTTPS اجرا می‌شود، کوکی هم فقط روی HTTPS ارسال شود
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_name(SESSION_NAME);
    session_start();
}

/**
 * ورود کاربر — با ایمیل یا نام کاربری
 */
function authLogin(string $login, string $password, bool $remember = false): array
{
    $db = Database::getConnection();

    // بررسی محدودیت تلاش ورود
    if (isRateLimited($login)) {
        return ['success' => false, 'message' => 'تعداد تلاش بیش از حد مجاز. لطفاً ۱۵ دقیقه صبر کنید.'];
    }

    // یافتن کاربر با ایمیل یا نام کاربری
    $stmt = $db->prepare(
        'SELECT id, name, username, email, password, role, is_active, avatar
         FROM ' . tbl('users') . '
         WHERE email = ? OR username = ?
         LIMIT 1'
    );
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        recordFailedAttempt($login);
        return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
    }

    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'حساب کاربری شما غیرفعال شده است'];
    }

    // به‌روزرسانی هش در صورت تغییر الگوریتم یا هزینه
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $db->prepare('UPDATE ' . tbl('users') . ' SET password = ? WHERE id = ?')
           ->execute([$newHash, $user['id']]);
    }

    establishSession($user, $remember);
    clearFailedAttempts($login);

    $db->prepare('UPDATE ' . tbl('users') . ' SET last_login_at = NOW() WHERE id = ?')
       ->execute([$user['id']]);

    logActivity('login', 'user', (int) $user['id'], 'ورود به سیستم');

    return [
        'success' => true,
        'message' => 'ورود موفقیت‌آمیز بود',
        'data'    => ['user' => publicUserFields($user)],
    ];
}

/**
 * ثبت‌نام کاربر جدید
 */
function authRegister(string $name, string $email, string $password, ?string $username = null): array
{
    $db = Database::getConnection();

    $username = $username !== null && trim($username) !== ''
        ? trim($username)
        : generateUsername($email);

    $errors = validateRegistration($name, $email, $password, $username);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' | ', $errors)];
    }

    // بررسی تکراری بودن ایمیل یا نام کاربری
    $stmt = $db->prepare('SELECT email, username FROM ' . tbl('users') . ' WHERE email = ? OR username = ? LIMIT 1');
    $stmt->execute([$email, $username]);

    if ($existing = $stmt->fetch()) {
        return [
            'success' => false,
            'message' => $existing['email'] === $email
                ? 'این ایمیل قبلاً ثبت شده است'
                : 'این نام کاربری قبلاً انتخاب شده است',
        ];
    }

    // نقش پیش‌فرض کاربران جدید از تنظیمات خوانده می‌شود
    $defaultRole = getOption('default_role', 'subscriber');
    if (!isValidRole($defaultRole) || $defaultRole === 'administrator') {
        $defaultRole = 'subscriber';
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('users') . ' (name, username, email, password, role)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $username, $email, $hashedPassword, $defaultRole]);

    $userId = (int) $db->lastInsertId();

    logActivity('register', 'user', $userId, 'ثبت‌نام کاربر جدید: ' . $name);

    return [
        'success' => true,
        'message' => 'ثبت‌نام با موفقیت انجام شد',
        'data'    => ['user_id' => $userId],
    ];
}

/**
 * خروج کاربر
 */
function authLogout(): void
{
    if (!empty($_SESSION['user_id'])) {
        $db = Database::getConnection();
        $db->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE id = ?')->execute([session_id()]);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    // حذف کوکی «مرا به خاطر بسپار»
    setcookie('fardcms_remember', '', ['expires' => time() - 42000, 'path' => '/']);

    session_destroy();
}

/**
 * بررسی ورود کاربر
 */
function isLoggedIn(): bool
{
    if (empty($_SESSION['user_id'])) {
        return attemptRememberLogin();
    }

    // بررسی انقضای session
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_LIFETIME)) {
        authLogout();
        return false;
    }

    // بررسی تطابق User Agent برای جلوگیری از سرقت session
    // (بر خلاف IP، با تغییر شبکه کاربر تغییر نمی‌کند)
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== currentUserAgentHash()) {
        authLogout();
        return false;
    }

    touchSession();

    return true;
}

/**
 * دریافت کاربر فعلی از session
 */
function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id'       => (int) $_SESSION['user_id'],
        'name'     => $_SESSION['user_name'],
        'username' => $_SESSION['user_username'] ?? '',
        'email'    => $_SESSION['user_email'],
        'role'     => $_SESSION['user_role'],
        'avatar'   => $_SESSION['user_avatar'] ?? null,
        'caps'     => roleCaps($_SESSION['user_role'] ?? ''),
    ];
}

/**
 * دریافت رکورد کامل کاربر فعلی از دیتابیس
 */
function getCurrentUserRecord(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    $db = Database::getConnection();
    $stmt = $db->prepare(
        'SELECT id, name, username, email, role, is_active, avatar, bio, created_at, last_login_at
         FROM ' . tbl('users') . ' WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$_SESSION['user_id']]);

    return $stmt->fetch() ?: null;
}

/**
 * شناسه کاربر فعلی (۰ اگر وارد نشده باشد)
 */
function currentUserId(): int
{
    return isLoggedIn() ? (int) $_SESSION['user_id'] : 0;
}

/**
 * آیا کاربر مدیر کل است؟
 */
function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'administrator';
}

/**
 * آیا کاربر به پنل مدیریت دسترسی دارد؟
 */
function canAccessAdmin(): bool
{
    return currentUserCan('edit_posts') || currentUserCan('manage_settings');
}

/**
 * تولید توکن امن تصادفی
 */
function generateSecureToken(int $length = 64): string
{
    return bin2hex(random_bytes((int) max(8, $length / 2)));
}

/**
 * تولید و ذخیره توکن CSRF
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateSecureToken(64);
    }

    return $_SESSION['csrf_token'];
}

/**
 * اعتبارسنجی توکن CSRF
 */
function validateCsrfToken(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ایجاد توکن بازیابی رمز عبور
 */
function createPasswordResetToken(int $userId): string
{
    $db = Database::getConnection();
    $token = generateSecureToken(64);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // حذف توکن‌های قبلی همان کاربر
    $db->prepare('DELETE FROM ' . tbl('password_resets') . ' WHERE user_id = ?')->execute([$userId]);

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('password_resets') . ' (user_id, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$userId, hash('sha256', $token), $expiresAt]);

    return $token;
}

/**
 * اعتبارسنجی توکن بازیابی رمز
 */
function validateResetToken(string $token): ?int
{
    $db = Database::getConnection();

    $stmt = $db->prepare(
        'SELECT user_id FROM ' . tbl('password_resets') . '
         WHERE token = ? AND used = 0 AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $result = $stmt->fetch();

    return $result ? (int) $result['user_id'] : null;
}

/**
 * علامت‌گذاری توکن به عنوان استفاده شده
 */
function markResetTokenUsed(string $token): void
{
    $db = Database::getConnection();
    $db->prepare('UPDATE ' . tbl('password_resets') . ' SET used = 1 WHERE token = ?')
       ->execute([hash('sha256', $token)]);
}

/**
 * تغییر رمز عبور یک کاربر و بی‌اعتبار کردن نشست‌های دیگر
 */
function setUserPassword(int $userId, string $password, bool $keepCurrentSession = true): void
{
    $db = Database::getConnection();

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $db->prepare('UPDATE ' . tbl('users') . ' SET password = ? WHERE id = ?')->execute([$hash, $userId]);

    // خروج از تمام نشست‌های دیگر برای امنیت بیشتر
    if ($keepCurrentSession && session_status() === PHP_SESSION_ACTIVE) {
        $db->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ? AND id <> ?')
           ->execute([$userId, session_id()]);
    } else {
        $db->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ?')->execute([$userId]);
    }
}

// ─── توابع کمکی داخلی ──────────────────────────────────────

/**
 * فیلدهای قابل نمایش عمومی یک کاربر
 */
function publicUserFields(array $user): array
{
    return [
        'id'       => (int) $user['id'],
        'name'     => $user['name'],
        'username' => $user['username'] ?? '',
        'email'    => $user['email'],
        'role'     => $user['role'],
        'avatar'   => $user['avatar'] ?? null,
        'caps'     => roleCaps($user['role']),
    ];
}

/**
 * ساخت session پس از احراز هویت موفق
 */
function establishSession(array $user, bool $remember): void
{
    // بازسازی session ID برای جلوگیری از session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_username'] = $user['username'] ?? '';
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_avatar']   = $user['avatar'] ?? null;
    $_SESSION['login_time']    = time();
    $_SESSION['user_agent']    = currentUserAgentHash();

    saveSession((int) $user['id'], $remember);
}

/**
 * ثبت یا به‌روزرسانی نشست در دیتابیس
 */
function saveSession(int $userId, bool $remember): void
{
    $db = Database::getConnection();

    $rememberToken = $remember ? generateSecureToken(64) : null;
    $hashedToken = $rememberToken !== null ? hash('sha256', $rememberToken) : null;

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('sessions') . ' (id, user_id, ip_address, user_agent, last_activity, remember_token)
         VALUES (?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE last_activity = NOW(), ip_address = VALUES(ip_address),
                                 user_agent = VALUES(user_agent), remember_token = VALUES(remember_token)'
    );
    $stmt->execute([
        session_id(),
        $userId,
        getClientIp(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        $hashedToken,
    ]);

    if ($rememberToken !== null) {
        setcookie('fardcms_remember', $userId . ':' . $rememberToken, [
            'expires'  => time() + REMEMBER_LIFETIME,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
}

/**
 * به‌روزرسانی آخرین فعالیت نشست (حداکثر هر ۵ دقیقه یک‌بار)
 */
function touchSession(): void
{
    $last = $_SESSION['last_touch'] ?? 0;

    if (time() - $last < 300) {
        return;
    }

    $_SESSION['last_touch'] = time();

    try {
        Database::getConnection()
            ->prepare('UPDATE ' . tbl('sessions') . ' SET last_activity = NOW() WHERE id = ?')
            ->execute([session_id()]);
    } catch (Throwable $e) {
        error_log('Failed to touch session: ' . $e->getMessage());
    }
}

/**
 * تلاش برای ورود خودکار با کوکی «مرا به خاطر بسپار»
 */
function attemptRememberLogin(): bool
{
    $cookie = $_COOKIE['fardcms_remember'] ?? '';

    if ($cookie === '' || !str_contains($cookie, ':')) {
        return false;
    }

    [$userId, $token] = explode(':', $cookie, 2);
    $userId = (int) $userId;

    if ($userId <= 0 || $token === '') {
        return false;
    }

    try {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT u.id, u.name, u.username, u.email, u.role, u.is_active, u.avatar, s.id AS session_id
             FROM ' . tbl('sessions') . ' s
             INNER JOIN ' . tbl('users') . ' u ON u.id = s.user_id
             WHERE s.user_id = ? AND s.remember_token = ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$userId, hash('sha256', $token)]);
        $user = $stmt->fetch();

        if (!$user) {
            setcookie('fardcms_remember', '', ['expires' => time() - 42000, 'path' => '/']);
            return false;
        }

        // نشست قبلی حذف می‌شود تا توکن یک‌بار مصرف باشد (چرخش توکن)
        $db->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE id = ?')->execute([$user['session_id']]);

        establishSession($user, true);

        return true;
    } catch (Throwable $e) {
        error_log('Remember-me login failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * اعتبارسنجی اطلاعات ثبت‌نام
 *
 * @return string[]
 */
function validateRegistration(string $name, string $email, string $password, string $username = ''): array
{
    $errors = [];

    if (mb_strlen(trim($name), 'UTF-8') < 2) {
        $errors[] = 'نام باید حداقل ۲ کاراکتر باشد';
    }

    if (mb_strlen(trim($name), 'UTF-8') > 100) {
        $errors[] = 'نام نمی‌تواند بیش از ۱۰۰ کاراکتر باشد';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'فرمت ایمیل نامعتبر است';
    }

    if ($username !== '' && !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'نام کاربری باید ۳ تا ۵۰ کاراکتر و شامل حروف لاتین، عدد، نقطه یا خط تیره باشد';
    }

    $errors = array_merge($errors, validatePasswordStrength($password));

    return $errors;
}

/**
 * بررسی قدرت رمز عبور
 *
 * @return string[]
 */
function validatePasswordStrength(string $password): array
{
    $errors = [];

    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $errors[] = 'رمز عبور باید حداقل ' . toPersianDigits((string) MIN_PASSWORD_LENGTH) . ' کاراکتر باشد';
    }

    if (strlen($password) > 200) {
        $errors[] = 'رمز عبور بیش از حد طولانی است';
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'رمز عبور باید شامل حروف بزرگ، کوچک و عدد باشد';
    }

    return $errors;
}

/**
 * ساخت نام کاربری پیشنهادی از ایمیل
 */
function generateUsername(string $email): string
{
    $base = preg_replace('/[^a-zA-Z0-9._-]/', '', strstr($email, '@', true) ?: 'user');
    $base = substr($base, 0, 40);

    if (strlen($base) < 3) {
        $base = 'user' . $base;
    }

    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT id FROM ' . tbl('users') . ' WHERE username = ? LIMIT 1');

    $username = $base;
    $suffix = 1;

    while (true) {
        $stmt->execute([$username]);

        if (!$stmt->fetch()) {
            return $username;
        }

        $username = $base . (++$suffix);
    }
}

/**
 * آدرس IP کاربر
 */
function getClientIp(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/**
 * اثر انگشت مرورگر کاربر
 */
function currentUserAgentHash(): string
{
    return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . SECRET_KEY);
}

// ─── محدودیت تلاش ورود (بر پایه فایل) ──────────────────────

function getRateLimitFile(): string
{
    return sys_get_temp_dir() . '/fardcms_login_limits.json';
}

function isRateLimited(string $identifier): bool
{
    $file = getRateLimitFile();

    if (!file_exists($file)) {
        return false;
    }

    $data = json_decode((string) file_get_contents($file), true) ?: [];
    $key = hash('sha256', $identifier . SECRET_KEY);

    if (!isset($data[$key]['attempts'])) {
        return false;
    }

    $windowStart = time() - RATE_LIMIT_WINDOW;
    $recent = array_filter($data[$key]['attempts'], fn($t) => $t > $windowStart);

    return count($recent) >= RATE_LIMIT_MAX;
}

function recordFailedAttempt(string $identifier): void
{
    $file = getRateLimitFile();
    $data = json_decode(file_exists($file) ? (string) file_get_contents($file) : '{}', true) ?: [];
    $key = hash('sha256', $identifier . SECRET_KEY);

    $windowStart = time() - RATE_LIMIT_WINDOW;
    $attempts = $data[$key]['attempts'] ?? [];
    $attempts[] = time();

    $data[$key]['attempts'] = array_values(array_filter($attempts, fn($t) => $t > $windowStart));

    // پاک‌سازی رکوردهای منقضی سایر کاربران
    foreach ($data as $k => $entry) {
        if (empty(array_filter($entry['attempts'] ?? [], fn($t) => $t > $windowStart))) {
            unset($data[$k]);
        }
    }

    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clearFailedAttempts(string $identifier): void
{
    $file = getRateLimitFile();

    if (!file_exists($file)) {
        return;
    }

    $data = json_decode((string) file_get_contents($file), true) ?: [];
    unset($data[hash('sha256', $identifier . SECRET_KEY)]);

    file_put_contents($file, json_encode($data), LOCK_EX);
}
