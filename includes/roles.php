<?php
/**
 * نقش‌ها و دسترسی‌ها (Capabilities)
 *
 * ساختار مشابه وردپرس: هر نقش مجموعه‌ای از دسترسی‌ها دارد و بررسی‌ها
 * همیشه بر اساس دسترسی انجام می‌شود، نه نام نقش.
 */

/**
 * فهرست کامل نقش‌ها و دسترسی‌های آن‌ها
 *
 * @return array<string, array{label:string, caps:string[]}>
 */
function allRoles(): array
{
    return [
        'administrator' => [
            'label' => 'مدیر کل',
            'caps'  => [
                'read', 'upload_files',
                'edit_posts', 'publish_posts', 'delete_posts',
                'edit_others_posts', 'delete_others_posts', 'read_private_posts',
                'manage_categories', 'moderate_comments',
                'manage_menus', 'manage_media', 'manage_themes',
                'manage_users', 'manage_settings', 'view_activity',
            ],
        ],
        'editor' => [
            'label' => 'ویراستار',
            'caps'  => [
                'read', 'upload_files',
                'edit_posts', 'publish_posts', 'delete_posts',
                'edit_others_posts', 'delete_others_posts', 'read_private_posts',
                'manage_categories', 'moderate_comments', 'manage_media', 'manage_menus',
            ],
        ],
        'author' => [
            'label' => 'نویسنده',
            'caps'  => [
                'read', 'upload_files',
                'edit_posts', 'publish_posts', 'delete_posts',
            ],
        ],
        'contributor' => [
            'label' => 'مشارکت‌کننده',
            'caps'  => ['read', 'edit_posts'],
        ],
        'subscriber' => [
            'label' => 'کاربر',
            'caps'  => ['read'],
        ],
    ];
}

/**
 * دسترسی‌های یک نقش
 *
 * @return string[]
 */
function roleCaps(string $role): array
{
    return allRoles()[$role]['caps'] ?? [];
}

/**
 * برچسب فارسی یک نقش
 */
function roleLabel(string $role): string
{
    return allRoles()[$role]['label'] ?? $role;
}

/**
 * آیا نقش معتبر است؟
 */
function isValidRole(string $role): bool
{
    return isset(allRoles()[$role]);
}

/**
 * آیا کاربر فعلی دسترسی مشخصی دارد؟
 */
function currentUserCan(string $capability): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    return in_array($capability, roleCaps($_SESSION['user_role'] ?? ''), true);
}

/**
 * آیا کاربر فعلی می‌تواند این نوشته را ویرایش کند؟
 *
 * نویسنده‌ها فقط نوشته‌های خودشان را ویرایش می‌کنند؛ ویراستار و مدیر
 * به نوشته‌های دیگران هم دسترسی دارند.
 */
function canEditPost(array $post): bool
{
    if (!currentUserCan('edit_posts')) {
        return false;
    }

    if ((int) ($post['author_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0)) {
        return true;
    }

    return currentUserCan('edit_others_posts');
}

/**
 * آیا کاربر فعلی می‌تواند این نوشته را حذف کند؟
 */
function canDeletePost(array $post): bool
{
    if (!currentUserCan('delete_posts')) {
        return false;
    }

    if ((int) ($post['author_id'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0)) {
        return true;
    }

    return currentUserCan('delete_others_posts');
}
