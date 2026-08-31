<?php
require (__DIR__ . '/components/helpers.php');
require (__DIR__ . '/../../config/variables.php');
require (__DIR__ . '/../../api/Controllers/Auth/AuthController.php');
$app_config = require __DIR__ . '/../../config/app.php';
$AuthController = new AuthController($conn, true);
$isAuth = ($AuthController->isAuth())['data']['authenticated'];
$admin_context = $isAuth ? admin_context($conn) : [];
$admin_currency_code = (string) ($app_config['restaurant']['currency'] ?? 'JOD');
$admin_currency_symbol = $admin_currency_code === 'JOD'
    ? 'د.أ'
    : (string) ($app_config['locales']['en']['restaurant']['currency'] ?? $admin_currency_code);



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
    'restaurants'  => 'restaurants.php',
    'orders'       => 'orders.php',
    'takeaway'     => 'takeaway.php',
    'menu'         => 'menu.php',
    'tables'       => 'tables.php',
    'inventory'    => 'inventory.php',
    'invoices'     => 'invoices.php',
    'staff'        => 'employees.php',
    'employees'    => 'employees.php',
    'managers'     => 'employees.php',
    'discounts'    => 'discounts.php',
    'log'          => 'log.php',
    'settings'     => 'settings.php',
];

$sections = [
    'dashboard'    => 'Dashboard',
    'restaurants'  => 'Restaurants',
    'orders'       => 'Orders',
    'takeaway'     => 'Takeaway',
    'menu'         => 'Menu Items',
    'tables'       => 'Tables',
    'inventory'    => 'Inventory',
    'invoices'     => 'Invoices',
    'staff'        => 'Staff',
    'employees'    => 'Staff',
    'managers'     => 'Managers',
    'discounts'    => 'Discounts',
    'log'          => 'Activity Log',
    'reservations' => 'Reservations',
    'settings'     => 'Settings',
];

$template = [
    'body'   => '',
    'footer' =>
        '<script src="' . htmlspecialchars(admin_asset_url('js/dashboard.js'), ENT_QUOTES) . '"></script>',
];

$template['header']['body'] =
    '<script>window.ADMIN_CURRENCY = ' .
    json_encode([
        'code' => $admin_currency_code,
        'symbol' => $admin_currency_symbol,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
    ';</script>' .
    '<link rel="stylesheet" href="' . htmlspecialchars(admin_asset_url('css/admin.css'), ENT_QUOTES) . '">';

ob_start();
    $auth_layout  = false;
    $active_page  = $page;
    $menu_section_title = [
        'foods' => 'Foods',
        'addons' => 'Food Addons',
        'categories' => 'Categories',
    ];
    $page_title   = $page === 'menu'
        ? ($menu_section_title[$_GET['menu_section'] ?? 'foods'] ?? 'Foods')
        : ($sections[$page] ?? 'Dashboard');
    $page_subtitle = "Welcome back, here's what's happening today";

if(!$isAuth){
    $template['title'] = 'Login | Savora Admin';
    $template['footer'] .=
    '<script src="' . htmlspecialchars(admin_asset_url('js/login.js'), ENT_QUOTES) . '"></script>';
    $auth_layout = true;
    include __DIR__ . '/pages/login.php';
}else{
    if (!empty($admin_context['is_super_admin']) && empty($admin_context['selected_restaurant_id']) && $page !== 'restaurants' && $page !== 'logout') {
        header('Location: ?page=restaurants');
        exit;
    }

    if (!admin_page_allowed($admin_context, $page)) {
        header('Location: ' . admin_url('dashboard', $admin_context));
        exit;
    }

    if ($page === 'logout') {
        if($AuthController->logout()["success"]){
            header("Location:".$uri."/admin");
        }

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
}



$template['body'] = ob_get_clean();

$template['footer'] =
    '<script>window.ADMIN_CONTEXT = ' .
    json_encode($admin_context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
    ';window.ADMIN_CURRENCY = ' .
    json_encode([
        'code' => $admin_currency_code,
        'symbol' => $admin_currency_symbol,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
    ';</script>' .
    $template['footer'];

include __DIR__ . '/components/template.php';
