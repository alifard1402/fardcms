<?php
/**
 * GET|POST /api/users/profile.php
 * مشاهده و ویرایش پروفایل کاربر فعلی
 */

require_once __DIR__ . '/../bootstrap.php';

$method = apiBootstrap(['GET', 'POST']);
requireAuth();

$userId = currentUserId();

if ($method === 'GET') {
    jsonSuccess([
        'user'     => getUser($userId),
        'sessions' => getUserSessions($userId),
    ]);
}

$input = getJsonInput();

// کاربر نمی‌تواند نقش یا وضعیت فعال بودن خودش را تغییر دهد
unset($input['role'], $input['is_active'], $input['id']);

$result = updateUser($userId, $input);

if (!$result['success']) {
    jsonError($result['message']);
}

// همگام‌سازی اطلاعات نمایشی در session
$updated = $result['data']['user'];
$_SESSION['user_name'] = $updated['name'];
$_SESSION['user_email'] = $updated['email'];
$_SESSION['user_username'] = $updated['username'];
$_SESSION['user_avatar'] = $updated['avatar'];

jsonSuccess($result['data'], $result['message']);
