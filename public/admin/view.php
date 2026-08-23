<?php
require (__DIR__ . '/components/helpers.php');

function section_placeholder(string $title): string
{
    return
        '<div class="card placeholder-section">' .
            '<div class="card-body">' .
                '<div class="placeholder-icon"><i class="bi bi-egg-fried"></i></div>' .
                '<h4 class="fw-bold">' . htmlspecialchars($title) . '</h4>' .
                '<p class="text-secondary mb-3">This section is coming soon.</p>' .
                '<a class="btn btn-primary" href="view.php?view=dashboard"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>' .
            '</div>' .
        '</div>';
}

$page = $_GET['view'] ?? $_GET['page'] ?? 'dashboard';

$page = strtolower(trim($page));
$page = preg_replace('/[^a-z0-9_\-]/', '', $page);

$pages = [
    'dashboard'    => 'dashboard.php',
    'orders'       => 'orders.php',
    'menu'         => 'menu.php',
    'tables'       => 'tables.php',
    'staff'        => 'employees.php',
    'employees'    => 'employees.php',
    'discounts'    => 'discounts.php',
    'log'          => 'log.php',
];

$sections = [
    'dashboard'    => 'Dashboard',
    'orders'       => 'Orders',
    'menu'         => 'Menu Items',
    'tables'       => 'Tables',
    'staff'        => 'Staff',
    'employees'    => 'Staff',
    'discounts'    => 'Discounts',
    'log'          => 'Activity Log',
    'reservations' => 'Reservations',
    'settings'     => 'Settings',
];

$template = [
    'title'  => 'Restaurant Management Panel',
    'body'   => '',
    'footer' =>
        '<script src="' . htmlspecialchars(admin_asset_url('js/dashboard.js'), ENT_QUOTES) . '"></script>',
];

$template['header']['body'] =
    '<link rel="stylesheet" href="' . htmlspecialchars(admin_asset_url('css/admin.css'), ENT_QUOTES) . '">';

$auth_layout  = false;
$active_page  = $page;
$page_title   = $sections[$page] ?? 'Dashboard';
$page_subtitle = "Welcome back, here's what's happening today";

ob_start();

if ($page === 'login') {
    $auth_layout = true;
    $template['title'] = 'Login | Savora Admin';
    include __DIR__ . '/pages/login.php';
} elseif ($page === 'logout') {
    $active_page = 'dashboard';
    include __DIR__ . '/pages/dashboard.php';
} elseif (isset($pages[$page])) {
    $page_path = __DIR__ . '/pages/' . $pages[$page];
    if (is_file($page_path) && filesize($page_path) > 5) {
        include $page_path;
    } else {
        $placeholder_title = $sections[$page] ?? ucfirst($page);
        echo section_placeholder($placeholder_title);
    }
} else {
    $placeholder_title = $sections[$page] ?? ucfirst($page);
    echo section_placeholder($placeholder_title);
}

$template['body'] = ob_get_clean();

include __DIR__ . '/components/template.php';