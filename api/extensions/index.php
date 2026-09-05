<?php
/**
 * GET /api/extensions/index.php
 * فهرست قالب‌ها و افزونه‌های نصب‌شده
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('manage_settings');

jsonSuccess([
    'themes'       => installedThemes(),
    'active_theme' => (string) getOption('active_theme', 'default'),
    'plugins'      => installedPlugins(),
    'can_install'  => class_exists('ZipArchive') && is_writable(THEMES_PATH)
                      && is_writable(pluginsPath()),
    'max_size'     => PACKAGE_MAX_ZIP,
]);
