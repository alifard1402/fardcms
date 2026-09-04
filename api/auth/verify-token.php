<?php
/**
 * GET /api/auth/verify-token.php?token=...
 * بررسی معتبر بودن توکن بازیابی پیش از نمایش فرم
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '' || validateResetToken($token) === null) {
    jsonError('لینک بازیابی نامعتبر یا منقضی شده است', 410);
}

jsonSuccess(['valid' => true]);
