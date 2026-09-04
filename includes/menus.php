<?php
/**
 * فهرست‌های ناوبری (Navigation Menus)
 */

/** جایگاه‌های قابل استفاده در قالب */
function menuLocations(): array
{
    return [
        'primary' => 'فهرست اصلی (سربرگ)',
        'footer'  => 'فهرست پاورقی',
        'social'  => 'شبکه‌های اجتماعی',
    ];
}

/**
 * دریافت تمام فهرست‌ها
 *
 * @return array<int, array<string, mixed>>
 */
function getMenus(): array
{
    $rows = Database::getConnection()
        ->query('SELECT id, name, slug, location FROM ' . tbl('menus') . ' ORDER BY id ASC')
        ->fetchAll();

    return array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['location_label'] = menuLocations()[$row['location']] ?? '';
        $row['items'] = getMenuItems($row['id']);

        return $row;
    }, $rows);
}

/**
 * دریافت آیتم‌های یک فهرست به صورت درختی
 *
 * @return array<int, array<string, mixed>>
 */
function getMenuItems(int $menuId): array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT mi.id, mi.parent_id, mi.title, mi.url, mi.type, mi.object_id,
                mi.target, mi.icon, mi.menu_order,
                p.slug AS object_slug, p.type AS object_type, p.status AS object_status,
                t.slug AS term_slug, t.taxonomy AS term_taxonomy
         FROM ' . tbl('menu_items') . ' mi
         LEFT JOIN ' . tbl('posts') . " p ON p.id = mi.object_id AND mi.type IN ('post', 'page')
         LEFT JOIN " . tbl('terms') . " t ON t.id = mi.object_id AND mi.type IN ('category', 'tag')
         WHERE mi.menu_id = ?
         ORDER BY mi.menu_order ASC, mi.id ASC"
    );
    $stmt->execute([$menuId]);

    $items = array_map(function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['parent_id'] = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
        $row['object_id'] = $row['object_id'] !== null ? (int) $row['object_id'] : null;
        $row['menu_order'] = (int) $row['menu_order'];
        $row['resolved_url'] = resolveMenuItemUrl($row);

        return $row;
    }, $stmt->fetchAll());

    return buildMenuTree($items);
}

/**
 * دریافت فهرست یک جایگاه مشخص (برای استفاده در قالب)
 *
 * @return array<int, array<string, mixed>>
 */
function getMenuByLocation(string $location): array
{
    $stmt = Database::getConnection()->prepare(
        'SELECT id FROM ' . tbl('menus') . ' WHERE location = ? LIMIT 1'
    );
    $stmt->execute([$location]);
    $menu = $stmt->fetch();

    return $menu ? getMenuItems((int) $menu['id']) : [];
}

/**
 * ایجاد یا به‌روزرسانی یک فهرست
 *
 * @return array{success: bool, message: string, data?: array<string, mixed>}
 */
function saveMenu(array $data, ?int $id = null): array
{
    $db = Database::getConnection();

    $name = trim((string) ($data['name'] ?? ''));

    if ($name === '') {
        return ['success' => false, 'message' => 'نام فهرست نمی‌تواند خالی باشد'];
    }

    $location = (string) ($data['location'] ?? '');
    if ($location !== '' && !isset(menuLocations()[$location])) {
        return ['success' => false, 'message' => 'جایگاه انتخابی نامعتبر است'];
    }

    $slug = uniqueSlug('menus', slugify($data['slug'] ?? $name), $id);

    try {
        // هر جایگاه فقط به یک فهرست تعلق دارد
        if ($location !== '') {
            $sql = 'UPDATE ' . tbl('menus') . " SET location = '' WHERE location = ?"
                 . ($id !== null ? ' AND id <> ?' : '');
            $params = $id !== null ? [$location, $id] : [$location];
            $db->prepare($sql)->execute($params);
        }

        if ($id === null) {
            $db->prepare('INSERT INTO ' . tbl('menus') . ' (name, slug, location) VALUES (?, ?, ?)')
               ->execute([$name, $slug, $location]);
            $id = (int) $db->lastInsertId();
        } else {
            $db->prepare('UPDATE ' . tbl('menus') . ' SET name = ?, slug = ?, location = ? WHERE id = ?')
               ->execute([$name, $slug, $location, $id]);
        }

        logActivity('save_menu', 'menu', $id, 'ذخیره فهرست: ' . $name);
    } catch (Throwable $e) {
        error_log('saveMenu failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'خطا در ذخیره فهرست'];
    }

    return ['success' => true, 'message' => 'فهرست ذخیره شد', 'data' => ['menu_id' => $id]];
}

/**
 * حذف یک فهرست همراه با آیتم‌هایش
 */
function deleteMenu(int $id): bool
{
    $db = Database::getConnection();

    $db->prepare('DELETE FROM ' . tbl('menu_items') . ' WHERE menu_id = ?')->execute([$id]);

    $stmt = $db->prepare('DELETE FROM ' . tbl('menus') . ' WHERE id = ?');
    $stmt->execute([$id]);

    return $stmt->rowCount() > 0;
}

/**
 * جایگزینی کامل آیتم‌های یک فهرست
 *
 * ساختار درختی دریافتی به رکوردهای مسطح با parent_id تبدیل می‌شود.
 *
 * @param array<int, array<string, mixed>> $items ساختار درختی آیتم‌ها
 */
function replaceMenuItems(int $menuId, array $items): array
{
    $db = Database::getConnection();

    try {
        $db->beginTransaction();

        $db->prepare('DELETE FROM ' . tbl('menu_items') . ' WHERE menu_id = ?')->execute([$menuId]);

        $stmt = $db->prepare(
            'INSERT INTO ' . tbl('menu_items') . '
             (menu_id, parent_id, title, url, type, object_id, target, icon, menu_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $insert = function (array $nodes, ?int $parentId) use (&$insert, $stmt, $db, $menuId): void {
            foreach (array_values($nodes) as $order => $node) {
                $title = trim((string) ($node['title'] ?? ''));

                if ($title === '') {
                    continue;
                }

                $type = pickAllowed(
                    $node['type'] ?? null,
                    ['custom', 'post', 'page', 'category', 'tag'],
                    'custom'
                );

                $stmt->execute([
                    $menuId,
                    $parentId,
                    mb_substr($title, 0, 100, 'UTF-8'),
                    mb_substr(trim((string) ($node['url'] ?? '')), 0, 500, 'UTF-8'),
                    $type,
                    !empty($node['object_id']) ? (int) $node['object_id'] : null,
                    ($node['target'] ?? '') === '_blank' ? '_blank' : '',
                    mb_substr((string) ($node['icon'] ?? ''), 0, 50, 'UTF-8'),
                    $order,
                ]);

                $childId = (int) $db->lastInsertId();

                if (!empty($node['children']) && is_array($node['children'])) {
                    $insert($node['children'], $childId);
                }
            }
        };

        $insert($items, null);

        $db->commit();

        logActivity('save_menu_items', 'menu', $menuId, 'به‌روزرسانی آیتم‌های فهرست');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('replaceMenuItems failed: ' . $e->getMessage());

        return ['success' => false, 'message' => 'خطا در ذخیره آیتم‌ها'];
    }

    return ['success' => true, 'message' => 'فهرست به‌روزرسانی شد'];
}

// ─── توابع کمکی داخلی ──────────────────────────────────────

/**
 * ساخت آدرس نهایی یک آیتم فهرست
 *
 * @param array<string, mixed> $item
 */
function resolveMenuItemUrl(array $item): string
{
    return match ($item['type']) {
        'page' => !empty($item['object_slug']) ? siteUrl($item['object_slug']) : '#',
        'post' => !empty($item['object_slug']) ? siteUrl('blog/' . $item['object_slug']) : '#',
        'category' => !empty($item['term_slug']) ? siteUrl('category/' . $item['term_slug']) : '#',
        'tag' => !empty($item['term_slug']) ? siteUrl('tag/' . $item['term_slug']) : '#',
        default => (string) ($item['url'] ?: '#'),
    };
}

/**
 * تبدیل آیتم‌های مسطح به ساختار درختی
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function buildMenuTree(array $items, ?int $parentId = null): array
{
    $branch = [];

    foreach ($items as $item) {
        if ($item['parent_id'] === $parentId) {
            $item['children'] = buildMenuTree($items, $item['id']);
            $branch[] = $item;
        }
    }

    return $branch;
}
