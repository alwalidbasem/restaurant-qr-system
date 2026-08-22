<?php

require __DIR__ . "/components/translator.php";
require __DIR__ . "/../../config/variables.php";
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
    } elseif ($table_number === false || $table_number === null || $table_number <= 0) {
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
        $restaurant_id = (int) $Restaurant_data['id'];
        include(__DIR__."/../../api/Controllers/TablesController.php");
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
                $orders_url = app_url('api/orders') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
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
                        . 'var url=new URL(ordersUrl,window.location.origin);'
                        . 'url.searchParams.set("session_order_key",key);'
                        . 'fetch(url.toString(),{headers:{Accept:"application/json","SESSION-ORDER-KEY":key}})'
                        . '.then(function(response){return response.ok?response.json():null;})'
                        . '.then(function(payload){'
                        . 'var rows=payload&&Array.isArray(payload.data)?payload.data:[];'
                        . 'var hasWaitingOwnOrder=rows.some(function(row){'
                        . 'return row.status==="waiting"&&(Number(row.table_id)===tableId||Number(row.table_number)===tableNumber);'
                        . '});'
                        . 'if(!hasWaitingOwnOrder){return;}'
                        . 'var nextUrl=new URL(orderPageUrl,window.location.origin);'
                        . 'nextUrl.searchParams.set("order_number",key);'
                        . 'window.location.replace(nextUrl.toString());'
                        . '})'
                        . '.catch(function(){});'
                        . '})();'
                        . '</script>';
                }
            } else {
                $menu_foods_url = app_url('api/menu-foods') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
                $orders_url = app_url('api/orders') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
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

    if ($restaurant_code !== '') {
        $Restaurant_model = new Restaurant($conn);
        $Restaurant_data = $Restaurant_model->getByCode($restaurant_code);

        if ($Restaurant_data) {
            $restaurant_id = (int) $Restaurant_data['id'];
            $menu_foods_url = app_url('api/menu-foods') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
            $orders_url = app_url('api/orders') . '?restaurant_id=' . rawurlencode((string) $restaurant_id);
            $template['header']['body'] .=
                '<script>'
                . 'window.RESTAURANT_ID = ' . json_encode($restaurant_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.RESTAURANT_CODE = ' . json_encode($restaurant_code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.MENU_FOODS_API_URL = ' . json_encode($menu_foods_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . 'window.ORDERS_API_URL = ' . json_encode($orders_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';'
                . '</script>';
        }
    }

    include(__DIR__ . "/pages/order.php");
    $template['body'] = ob_get_clean();
    $template['footer'] = '<script type="module" src="' . htmlspecialchars(client_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8') . '"></script>';
}

include $template_file;










?>
