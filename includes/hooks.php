<?php
/**
 * قلاب‌ها (hooks) — نقطه اتصال افزونه‌ها به هسته
 *
 * دو نوع قلاب داریم، درست مثل وردپرس:
 *
 *   کنش (action)  — «اینجا این اتفاق افتاد»؛ افزونه کاری انجام می‌دهد
 *   صافی (filter) — «این مقدار را می‌خواهم»؛ افزونه مقدار را تغییر می‌دهد
 *
 * چرا لازم است: بدون این لایه، هر قابلیت تازه یعنی دست بردن در فایل‌های
 * هسته. آن‌وقت به‌روزرسانی سیستم، تغییرهای کاربر را پاک می‌کند و هر
 * افزونه‌ای عملاً یک فورک از پروژه می‌شود.
 *
 * این فایل عمداً هیچ وابستگی‌ای ندارد و پیش از همه چیز بارگذاری می‌شود.
 */

/**
 * فهرست قلاب‌های ثبت‌شده
 *
 * ساختار: [نام قلاب][اولویت][] = ['fn' => callable, 'args' => int]
 *
 * @var array<string, array<int, array<int, array{fn: callable, args: int}>>>
 */
$GLOBALS['fardcms_hooks'] = [];

/**
 * ثبت یک تابع روی یک قلاب
 *
 * @param int $priority عدد کمتر یعنی زودتر اجرا می‌شود
 * @param int $args     تعداد آرگومان‌هایی که تابع می‌پذیرد
 */
function addFilter(string $hook, callable $callback, int $priority = 10, int $args = 1): void
{
    $GLOBALS['fardcms_hooks'][$hook][$priority][] = ['fn' => $callback, 'args' => $args];
}

/** ثبت یک تابع روی یک کنش — هم‌معنی addFilter است */
function addAction(string $hook, callable $callback, int $priority = 10, int $args = 1): void
{
    addFilter($hook, $callback, $priority, $args);
}

/**
 * آیا کسی روی این قلاب ثبت شده است؟
 */
function hasHook(string $hook): bool
{
    return !empty($GLOBALS['fardcms_hooks'][$hook]);
}

/**
 * عبور دادن یک مقدار از صافی‌ها
 *
 * هر صافی مقدار را می‌گیرد و مقدار تازه برمی‌گرداند. اگر صافی‌ای خطا
 * بدهد، مقدار پیش از آن حفظ می‌شود: یک افزونه معیوب نباید کل صفحه را
 * از کار بیندازد.
 *
 * @param mixed $value    مقدار اولیه
 * @param mixed ...$extra آرگومان‌های کمکی که به صافی‌ها پاس داده می‌شود
 * @return mixed
 */
function applyFilters(string $hook, mixed $value, mixed ...$extra): mixed
{
    if (empty($GLOBALS['fardcms_hooks'][$hook])) {
        return $value;
    }

    $byPriority = $GLOBALS['fardcms_hooks'][$hook];
    ksort($byPriority);

    foreach ($byPriority as $callbacks) {
        foreach ($callbacks as $entry) {
            $params = array_slice(array_merge([$value], $extra), 0, max(1, $entry['args']));

            try {
                $value = ($entry['fn'])(...$params);
            } catch (Throwable $e) {
                error_log("Hook '$hook' failed: " . $e->getMessage());
            }
        }
    }

    return $value;
}

/**
 * اجرای یک کنش
 *
 * مقدار بازگشتی معنا ندارد؛ فقط توابع ثبت‌شده اجرا می‌شوند.
 */
function doAction(string $hook, mixed ...$args): void
{
    if (empty($GLOBALS['fardcms_hooks'][$hook])) {
        return;
    }

    $byPriority = $GLOBALS['fardcms_hooks'][$hook];
    ksort($byPriority);

    foreach ($byPriority as $callbacks) {
        foreach ($callbacks as $entry) {
            try {
                ($entry['fn'])(...array_slice($args, 0, $entry['args']));
            } catch (Throwable $e) {
                error_log("Action '$hook' failed: " . $e->getMessage());
            }
        }
    }
}

/**
 * حذف تمام توابع ثبت‌شده روی یک قلاب
 *
 * بیشتر برای تست به کار می‌آید تا اجراهای پشت سر هم روی هم اثر نگذارند.
 */
function removeAllHooks(string $hook): void
{
    unset($GLOBALS['fardcms_hooks'][$hook]);
}
