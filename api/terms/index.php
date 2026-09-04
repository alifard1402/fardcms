<?php
/**
 * GET /api/terms/index.php?taxonomy=category|tag
 * فهرست دسته‌ها یا برچسب‌ها
 */

require_once __DIR__ . '/../bootstrap.php';

apiBootstrap(['GET']);
requireCap('edit_posts');

$taxonomy = (string) ($_GET['taxonomy'] ?? 'category');

$terms = getTerms($taxonomy, [
    'search'  => $_GET['search'] ?? '',
    'orderby' => $_GET['orderby'] ?? 'name',
    'order'   => $_GET['order'] ?? 'ASC',
]);

jsonSuccess([
    'terms' => $terms,
    'tree'  => $taxonomy === 'category' ? buildTermTree($terms) : $terms,
]);
