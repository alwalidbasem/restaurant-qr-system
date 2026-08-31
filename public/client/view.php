<?php

require __DIR__ . "/components/translator.php";
require __DIR__ . "/../../config/variables.php";
require_once __DIR__ . "/../../api/Controllers/helpers.php";
require_once __DIR__ . "/../../api/Models/RestaurantModel.php";

$site_lang = current_language_code();
$site_dir  = ($site_lang === 'ar') ? 'rtl' : 'ltr';

$template_file = __DIR__ . "/components/template.php";

$template['header']['title'] = t('meta_title', ['brand' => localized_app_config('brand.name', 'Ember & Vine')]);
$template['header']['meta']['description'] = t('meta_description', ['brand' => localized_app_config('brand.name', 'Ember & Vine')]);

ob_start();
$request_path = rtrim($uri, '/');
$route = basename($request_path);

$template['header']['body'] =
    '<link rel="stylesheet" href="' . htmlspecialchars(client_asset_url('css/main.css'), ENT_QUOTES, 'UTF-8') . '">'.
    '<link rel="stylesheet" href="' . htmlspecialchars(client_asset_url('css/status-page/status-page.css'), ENT_QUOTES, 'UTF-8') . '">'.
    '<script>window.I18N = ' . json_encode(site_language_dict(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';

function customer_preview_allowed(PDO $conn, int $restaurantId): bool {
    $employee = controllersHelper::currentEmployee($conn);
    if ($employee === null) {
        return false;
    }

    if (!controllersHelper::employeeHasPermission($employee, 'restaurant.update')) {
        return false;
    }

    if (controllersHelper::isSuperAdminEmployee($employee)) {
        return true;
    }

    return (int) ($employee['restaurant_id'] ?? 0) === $restaurantId;
}

if ($route === 'landing' || $route === '') {
    $table_number = filter_input(INPUT_GET, 't_n', FILTER_VALIDATE_INT);
    $restaurant_code = $_GET['r_code'] ?? $_GET['restaurant_code'] ?? $_GET['main_code'] ?? null;
    $restaurant_code = is_string($restaurant_code) ? trim($restaurant_code) : '';

    if ($restaurant_code === '') {
        http_response_code(400);
        $status_context = [
            'type' => 'error',
            'icon' => 'fa-key',
            'title' => t('restaurant_code_error_title'),
            'message' => t('restaurant_code_error_copy'),
            'action_label' => t('table_status_scan_again'),
            'action_url' => localized_app_url('landing')
        ];

        include(__DIR__ . "/pages/status.php");
        $template['body'] = ob_get_clean();
    } else {
        $Restaurant_model = new Restaurant($conn);
        $Restaurant_data = $Restaurant_model->getByCode($restaurant_code);

        if (!$Restaurant_data) {
            http_response_code(404);
            $status_context = [
                'type' => 'error',
                'icon' => 'fa-store-slash',
                'title' => t('restaurant_not_found_title'),
                'message' => t('restaurant_not_found_copy'),
                'action_label' => t('table_status_scan_again'),
                'action_url' => localized_app_url('landing')
            ];

            include(__DIR__ . "/pages/status.php");
            $template['body'] = ob_get_clean();
        } else {
        set_restaurant_website_settings($Restaurant_data);
        $restaurant_id = (int) $Restaurant_data['id'];
        $template['header']['title'] = (string) restaurant_website_setting('brand_name', $template['header']['title']);
        $template['header']['meta']['description'] = (string) restaurant_website_setting('hero_description', $template['header']['meta']['description']);
        $template['header']['body'] .= restaurant_website_css();
        $takeaway_enabled = (int) restaurant_website_setting('takeaway_enabled', $Restaurant_data['takeaway_enabled'] ?? 0) === 1;
        $preview_mode = isset($_GET['preview']);
        $takeaway_mode = filter_var($_GET['takeaway'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($preview_mode && !customer_preview_allowed($conn, $restaurant_id)) {
            http_response_code(403);
            $status_context = [
                'type' => 'error',
                'icon' => 'fa-lock',
                'title' => 'Preview locked',
                'message' => 'Restaurant preview requires a valid admin API key with restaurant.update permission.',
                'action_label' => t('table_status_scan_again'),
                'action_url' => localized_app_url('landing')
            ];

            include(__DIR__ . "/pages/status.php");
            $template['body'] = ob_get_clean();
        } else {
        if ($table_number === false || $table_number === null || $table_number <= 0) {
            if ((!$takeaway_enabled || !$takeaway_mode) && !$preview_mode) {
                http_response_code(400);
                $status_context = [
                    'type' => 'error',
                    'icon' => 'fa-triangle-exclamation',
                    'title' => t('table_status_number_error_title'),
                    'message' => t('table_status_number_error_copy'),
                    'action_label' => t('table_status_scan_again'),
                    'action_url' => localized_app_url('landing')
                ];

                include(__DIR__ . "/pages/status.php");
                $template['body'] = ob_get_clean();
            } else {
                $menu_foods_url = app_url('api/menu-foods') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
                $orders_url = app_url('api/orders') . '?restaurant_code=' . rawurlencode($restaurant_code);
                $order_page_url = app_url('order') . '?restaurant_code=' . rawurlencode($restaurant_code)
                    . ($takeaway_mode ? '&takeaway=true' : '')
                    . '&language=' . rawurlencode(current_language_code());
                $template['header']['body'] .=
                    '<script>'
                    . 'window.RESTAURANT_ID = ' . json_encode($restaurant_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.RESTAURANT_CODE = ' . json_encode($restaurant_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.TABLE_ID = 0;'
                    . 'window.TABLE_NUMBER = 0;'
                    . 'window.TAKEAWAY_ENABLED = ' . json_encode($takeaway_enabled, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.MENU_FOODS_API_URL = ' . json_encode($menu_foods_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.ORDERS_API_URL = ' . json_encode($orders_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.ORDER_PAGE_URL = ' . json_encode($order_page_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . '</script>';
                include(__DIR__ . "/pages/landing.php");
                $template['body'] = ob_get_clean();
                $template['footer'] = '<script type="module" src="' . htmlspecialchars(client_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
            }
        } else {
        include(__DIR__."/../../api/Controllers/Tables/TablesController.php");
        $Tables_Controllers = new TableController($conn);
        $Table_response = $Tables_Controllers->showByNumber($table_number, false, $restaurant_id);

        if (!$Table_response['success']) {
            http_response_code(404);
            $status_context = [
                'type' => 'error',
                'icon' => 'fa-circle-question',
                'title' => t('table_status_not_found_title'),
                'message' => t('table_status_not_found_copy'),
                'action_label' => t('table_status_scan_again'),
                'action_url' => localized_app_url('landing'),
                'table_number' => $table_number
            ];

            include(__DIR__ . "/pages/status.php");
            $template['body'] = ob_get_clean();
        } else {
            $Table_data = $Table_response['data'];
            $table_status = $Table_data['table_status'];

            if ($table_status !== "free") {
                $orders_url = app_url('api/orders') . '?restaurant_code=' . rawurlencode($restaurant_code);
                $order_page_url = app_url('order') . '?restaurant_code=' . rawurlencode($restaurant_code)
                    . '&t_n=' . rawurlencode((string) $Table_data['table_number'])
                    . '&language=' . rawurlencode(current_language_code());

                http_response_code(409);
                $status_context = [
                    'type' => 'busy',
                    'icon' => 'fa-chair',
                    'title' => t('table_status_busy_title'),
                    'message' => t('table_status_busy_copy'),
                    'action_label' => t('table_status_scan_again'),
                    'action_url' => localized_app_url('landing'),
                    'table_number' => $Table_data['table_number'],
                    'table_status' => $table_status
                ];

                include(__DIR__ . "/pages/status.php");
                $template['body'] = ob_get_clean();

                if ($table_status === 'waiting_order') {
                    $template['footer'] =
                        '<script>'
                        . '(function(){'
                        . 'var key="";'
                        . 'try{key=localStorage.getItem("emberVineSessionOrderKey")||"";}catch(err){}'
                        . 'if(!key){return;}'
                        . 'var ordersUrl=' . json_encode($orders_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                        . 'var orderPageUrl=' . json_encode($order_page_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                        . 'var tableId=' . json_encode((int) $Table_data['id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                        . 'var tableNumber=' . json_encode((int) $Table_data['table_number'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                        . 'var orderId=' . json_encode((int) ($Table_data['order_id'] ?? 0), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                        . 'if(!orderId){return;}'
                        . 'var url=new URL(ordersUrl,window.location.origin);'
                        . 'url.searchParams.set("session_order_key",key);'
                        . 'url.searchParams.set("order_id",String(orderId));'
                        . 'fetch(url.toString(),{credentials:"omit",headers:{Accept:"application/json","SESSION-ORDER-KEY":key}})'
                        . '.then(function(response){return response.ok?response.json():null;})'
                        . '.then(function(payload){'
                        . 'var rows=payload&&Array.isArray(payload.data)?payload.data:[];'
                        . 'var hasWaitingOwnOrder=rows.some(function(row){'
                        . 'return row.status==="waiting"&&(Number(row.table_id)===tableId||Number(row.table_number)===tableNumber);'
                        . '});'
                        . 'if(!hasWaitingOwnOrder){return;}'
                        . 'var nextUrl=new URL(orderPageUrl,window.location.origin);'
                        . 'nextUrl.searchParams.set("order_number",key);'
                        . 'nextUrl.searchParams.set("order_id",String(orderId));'
                        . 'window.location.replace(nextUrl.toString());'
                        . '})'
                        . '.catch(function(){});'
                        . '})();'
                        . '</script>';
                }
            } else {
                $menu_foods_url = app_url('api/menu-foods') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
                $orders_url = app_url('api/orders') . '?restaurant_code=' . rawurlencode($restaurant_code);
                $order_page_url = app_url('order') . '?restaurant_code=' . rawurlencode($restaurant_code)
                    . '&t_n=' . rawurlencode((string) $Table_data['table_number'])
                    . '&language=' . rawurlencode(current_language_code());
                $template['header']['body'] .=
                    '<script>'
                    . 'window.RESTAURANT_ID = ' . json_encode($restaurant_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.RESTAURANT_CODE = ' . json_encode($restaurant_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.TABLE_ID = ' . json_encode((int) $Table_data['id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.TABLE_NUMBER = ' . json_encode((int) $Table_data['table_number'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.MENU_FOODS_API_URL = ' . json_encode($menu_foods_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.ORDERS_API_URL = ' . json_encode($orders_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . 'window.ORDER_PAGE_URL = ' . json_encode($order_page_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                    . '</script>';
                include(__DIR__ . "/pages/landing.php");
                $template['body'] = ob_get_clean();
                $template['footer'] = '<script type="module" src="' . htmlspecialchars(client_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
            }
        }
        }
        }
        }
    }
} elseif ($route === 'status') {
    $status_context = [
        'type' => 'error',
        'icon' => 'fa-circle-exclamation',
        'title' => t('table_status_error_title'),
        'message' => t('table_status_error_copy'),
        'action_label' => t('table_status_scan_again'),
        'action_url' => localized_app_url('landing')
    ];

    include(__DIR__ . "/pages/status.php");
    $template['body'] = ob_get_clean();
} elseif ($route === 'order') {

    $restaurant_code = $_GET['r_code'] ?? $_GET['restaurant_code'] ?? $_GET['main_code'] ?? null;
    $restaurant_code = is_string($restaurant_code) ? trim($restaurant_code) : '';
    $table_number = filter_input(INPUT_GET, 't_n', FILTER_VALIDATE_INT);

    if ($restaurant_code !== '') {
        $Restaurant_model = new Restaurant($conn);
        $Restaurant_data = $Restaurant_model->getByCode($restaurant_code);

        if ($Restaurant_data) {
            set_restaurant_website_settings($Restaurant_data);
            $restaurant_id = (int) $Restaurant_data['id'];
            $template['header']['title'] = (string) restaurant_website_setting('brand_name', $template['header']['title']);
            $template['header']['meta']['description'] = (string) restaurant_website_setting('hero_description', $template['header']['meta']['description']);
            $template['header']['body'] .= restaurant_website_css();
            $menu_foods_url = app_url('api/menu-foods') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
            $orders_url = app_url('api/orders') . '?restaurant_code=' . rawurlencode($restaurant_code);
            $table_data = null;

            if ($table_number !== false && $table_number !== null && $table_number > 0) {
                require_once __DIR__ . "/../../api/Controllers/Tables/TablesController.php";
                $Tables_Controllers = new TableController($conn);
                $Table_response = $Tables_Controllers->showByNumber($table_number, false, $restaurant_id);
                if ($Table_response['success']) {
                    $table_data = $Table_response['data'];

                    if (($table_data['table_status'] ?? '') === 'free') {
                        $landing_url = app_url('') . '?restaurant_code=' . rawurlencode($restaurant_code)
                            . '&t_n=' . rawurlencode((string) $table_data['table_number'])
                            . '&language=' . rawurlencode(current_language_code());
                        header('Location: ' . $landing_url, true, 302);
                        exit;
                    }
                }
            }

            $template['header']['body'] .=
                '<script>'
                . 'window.RESTAURANT_ID = ' . json_encode($restaurant_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.RESTAURANT_CODE = ' . json_encode($restaurant_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.TABLE_ID = ' . json_encode($table_data ? (int) $table_data['id'] : 0, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.TABLE_NUMBER = ' . json_encode($table_data ? (int) $table_data['table_number'] : (($table_number !== false && $table_number !== null) ? (int) $table_number : 0), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.TAKEAWAY_ENABLED = ' . json_encode((int) restaurant_website_setting('takeaway_enabled', $Restaurant_data['takeaway_enabled'] ?? 0) === 1, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.MENU_FOODS_API_URL = ' . json_encode($menu_foods_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.ORDERS_API_URL = ' . json_encode($orders_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.TABLES_API_URL = ' . json_encode($table_data ? app_url('api/tables/' . (int) $table_data['id']) : '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . '</script>';
        }
    }

    include(__DIR__ . "/pages/order.php");
    $template['body'] = ob_get_clean();
    $template['footer'] = '<script type="module" src="' . htmlspecialchars(client_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
}

include $template_file;










?>
