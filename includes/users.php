<?php
/**
 * مدیریت کاربران (پنل مدیریت)
 */

/**
 * دریافت فهرست کاربران با فیلتر و صفحه‌بندی
 *
 * @return array{items: array<int, array<string, mixed>>, meta: array<string, int>}
 */
function getUsers(array $args = []): array
{
    $db = Database::getConnection();

    $page = max(1, (int) ($args['page'] ?? 1));
    $perPage = min(100, max(1, (int) ($args['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $conditions = [];
    $params = [];

    if (!empty($args['search'])) {
        $conditions[] = likeCondition(
            ['u.name', 'u.email', 'u.username'],
            trim((string) $args['search']),
            $params
        );
    }

    if (!empty($args['role']) && isValidRole((string) $args['role'])) {
        $conditions[] = 'u.role = :role';
        $params['role'] = $args['role'];
    }

    if (isset($args['is_active']) && $args['is_active'] !== '') {
        $conditions[] = 'u.is_active = :is_active';
        $params['is_active'] = (int) (bool) $args['is_active'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $orderMap = ['id' => 'u.id', 'name' => 'u.name', 'created' => 'u.created_at', 'login' => 'u.last_login_at'];
    $orderBy = $orderMap[$args['orderby'] ?? 'id'] ?? 'u.id';
    $order = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

    $stmt = $db->prepare('SELECT COUNT(*) AS total FROM ' . tbl('users') . " u $where");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        'SELECT u.id, u.name, u.username, u.email, u.role, u.is_active, u.avatar,
                u.bio, u.created_at, u.last_login_at,
                (SELECT COUNT(*) FROM ' . tbl('posts') . " p
                 WHERE p.author_id = u.id AND p.status <> 'trash') AS post_count
         FROM " . tbl('users') . " u
         $where
         ORDER BY $orderBy $order
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return [
        'items' => array_map('formatUserRow', $stmt->fetchAll()),
        'meta'  => paginationMeta($total, $page, $perPage),
    ];
}

/**
 * دریافت یک کاربر
 */
function getUser(int $id): ?array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id, name, username, email, role, is_active, avatar, bio, created_at, last_login_at
         FROM ' . tbl('users') . ' WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ? formatUserRow($row) : null;
}

/**
 * ایجاد کاربر توسط مدیر
 *
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function createUser(array $data): array
{
    $db = Database::getConnection();

    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $username = trim((string) ($data['username'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $role = (string) ($data['role'] ?? 'subscriber');

    if ($username === '') {
        $username = generateUsername($email !== '' ? $email : 'user');
    }

    if (!isValidRole($role)) {
        return ['success' => false, 'message' => 'نقش انتخابی نامعتبر است'];
    }

    $errors = validateRegistration($name, $email, $password, $username);
    if (!empty($errors)) {
        return ['success' => false, 'message' => implode(' | ', $errors)];
    }

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

    $stmt = $db->prepare(
        'INSERT INTO ' . tbl('users') . ' (name, username, email, password, role, is_active, bio)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name,
        $username,
        $email,
        password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]),
        $role,
        isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
        mb_substr(trim((string) ($data['bio'] ?? '')), 0, 500, 'UTF-8'),
    ]);

    $id = (int) $db->lastInsertId();

    logActivity('create_user', 'user', $id, 'ایجاد کاربر: ' . $name);

    return ['success' => true, 'message' => 'کاربر ایجاد شد', 'data' => ['user' => getUser($id)]];
}

/**
 * به‌روزرسانی یک کاربر توسط مدیر
 *
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function updateUser(int $id, array $data): array
{
    $db = Database::getConnection();

    $user = getUser($id);
    if ($user === null) {
        return ['success' => false, 'message' => 'کاربر یافت نشد'];
    }

    $fields = [];

    if (isset($data['name'])) {
        $name = trim((string) $data['name']);

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 100) {
            return ['success' => false, 'message' => 'نام باید بین ۲ تا ۱۰۰ کاراکتر باشد'];
        }

        $fields['name'] = $name;
    }

    if (isset($data['email'])) {
        $email = trim((string) $data['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'فرمت ایمیل نامعتبر است'];
        }

        $stmt = $db->prepare('SELECT id FROM ' . tbl('users') . ' WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $id]);

        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'این ایمیل قبلاً ثبت شده است'];
        }

        $fields['email'] = $email;
    }

    if (isset($data['username'])) {
        $username = trim((string) $data['username']);

        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            return ['success' => false, 'message' => 'نام کاربری نامعتبر است'];
        }

        $stmt = $db->prepare('SELECT id FROM ' . tbl('users') . ' WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $id]);

        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'این نام کاربری قبلاً انتخاب شده است'];
        }

        $fields['username'] = $username;
    }

    if (isset($data['role'])) {
        $role = (string) $data['role'];

        if (!isValidRole($role)) {
            return ['success' => false, 'message' => 'نقش انتخابی نامعتبر است'];
        }

        // مدیر نمی‌تواند نقش خودش را پایین بیاورد و سایت را بدون مدیر بگذارد
        if ($id === currentUserId() && $role !== 'administrator') {
            return ['success' => false, 'message' => 'نمی‌توانید نقش خودتان را تغییر دهید'];
        }

        if ($user['role'] === 'administrator' && $role !== 'administrator' && countAdministrators() <= 1) {
            return ['success' => false, 'message' => 'سایت باید حداقل یک مدیر کل داشته باشد'];
        }

        $fields['role'] = $role;
    }

    if (isset($data['is_active'])) {
        if ($id === currentUserId() && !$data['is_active']) {
            return ['success' => false, 'message' => 'نمی‌توانید حساب خودتان را غیرفعال کنید'];
        }

        $fields['is_active'] = (int) (bool) $data['is_active'];
    }

    if (isset($data['bio'])) {
        $fields['bio'] = mb_substr(trim((string) $data['bio']), 0, 500, 'UTF-8');
    }

    if (isset($data['avatar'])) {
        $fields['avatar'] = mb_substr(trim((string) $data['avatar']), 0, 500, 'UTF-8');
    }

    if (!empty($fields)) {
        $sets = implode(', ', array_map(fn($c) => "`$c` = :$c", array_keys($fields)));
        $db->prepare('UPDATE ' . tbl('users') . " SET $sets WHERE id = :id")
           ->execute($fields + ['id' => $id]);
    }

    // تغییر رمز عبور
    if (!empty($data['password'])) {
        $errors = validatePasswordStrength((string) $data['password']);

        if (!empty($errors)) {
            return ['success' => false, 'message' => implode(' | ', $errors)];
        }

        setUserPassword($id, (string) $data['password'], $id === currentUserId());
    }

    logActivity('update_user', 'user', $id, 'ویرایش کاربر: ' . ($fields['name'] ?? $user['name']));

    return ['success' => true, 'message' => 'تغییرات ذخیره شد', 'data' => ['user' => getUser($id)]];
}

/**
 * حذف یک کاربر
 *
 * @param int $reassignTo شناسه کاربری که محتوای حذف‌شده به او منتقل می‌شود (۰ = بدون نویسنده)
 * @return array{success: bool, message: string}
 */
function deleteUser(int $id, int $reassignTo = 0): array
{
    $db = Database::getConnection();

    if ($id === currentUserId()) {
        return ['success' => false, 'message' => 'نمی‌توانید حساب خودتان را حذف کنید'];
    }

    $user = getUser($id);
    if ($user === null) {
        return ['success' => false, 'message' => 'کاربر یافت نشد'];
    }

    if ($user['role'] === 'administrator' && countAdministrators() <= 1) {
        return ['success' => false, 'message' => 'سایت باید حداقل یک مدیر کل داشته باشد'];
    }

    try {
        $db->beginTransaction();

        // انتقال یا بی‌نویسنده کردن محتوا
        if ($reassignTo > 0 && getUser($reassignTo) !== null) {
            $db->prepare('UPDATE ' . tbl('posts') . ' SET author_id = ? WHERE author_id = ?')
               ->execute([$reassignTo, $id]);
            $db->prepare('UPDATE ' . tbl('media') . ' SET user_id = ? WHERE user_id = ?')
               ->execute([$reassignTo, $id]);
        } else {
            $db->prepare('UPDATE ' . tbl('posts') . ' SET author_id = NULL WHERE author_id = ?')->execute([$id]);
            $db->prepare('UPDATE ' . tbl('media') . ' SET user_id = NULL WHERE user_id = ?')->execute([$id]);
        }

        $db->prepare('DELETE FROM ' . tbl('sessions') . ' WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM ' . tbl('password_resets') . ' WHERE user_id = ?')->execute([$id]);
        $db->prepare('UPDATE ' . tbl('comments') . ' SET user_id = NULL WHERE user_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM ' . tbl('users') . ' WHERE id = ?')->execute([$id]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('deleteUser failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'خطا در حذف کاربر'];
    }

    logActivity('delete_user', 'user', $id, 'حذف کاربر: ' . $user['name']);

    return ['success' => true, 'message' => 'کاربر حذف شد'];
}

/**
 * شمارش مدیران کل فعال
 */
function countAdministrators(): int
{
    $row = Database::getConnection()
        ->query('SELECT COUNT(*) AS total FROM ' . tbl('users') . " WHERE role = 'administrator'")
        ->fetch();

    return (int) $row['total'];
}

/**
 * شمارش کاربران بر اساس نقش
 *
 * @return array<string, int>
 */
function getUserCounts(): array
{
    $rows = Database::getConnection()
        ->query('SELECT role, COUNT(*) AS total FROM ' . tbl('users') . ' GROUP BY role')
        ->fetchAll();

    $counts = array_fill_keys(array_keys(allRoles()), 0);
    $counts['all'] = 0;

    foreach ($rows as $row) {
        $counts[$row['role']] = (int) $row['total'];
        $counts['all'] += (int) $row['total'];
    }

    return $counts;
}

/**
 * نشست‌های فعال یک کاربر
 *
 * @return array<int, array<string, mixed>>
 */
function getUserSessions(int $userId): array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id, ip_address, user_agent, last_activity
         FROM ' . tbl('sessions') . '
         WHERE user_id = ?
         ORDER BY last_activity DESC'
    );
    $stmt->execute([$userId]);

    return array_map(function (array $row): array {
        // شناسه نشست هرگز به بیرون فرستاده نمی‌شود؛ فقط نشانگر نشست فعلی
        $row['is_current'] = hash_equals($row['id'], session_id());
        $row['last_activity_relative'] = timeAgo($row['last_activity']);
        unset($row['id']);

        return $row;
    }, $stmt->fetchAll());
}

/**
 * افزودن فیلدهای محاسبه‌شده به رکورد کاربر
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function formatUserRow(array $row): array
{
    $row['id'] = (int) $row['id'];
    $row['is_active'] = (bool) $row['is_active'];
    $row['post_count'] = (int) ($row['post_count'] ?? 0);
    $row['role_label'] = roleLabel($row['role']);
    $row['created_jalali'] = jalaliDate('j F Y', $row['created_at']);
    $row['last_login_relative'] = !empty($row['last_login_at']) ? timeAgo($row['last_login_at']) : 'هرگز';

    unset($row['password']);

    return $row;
}
