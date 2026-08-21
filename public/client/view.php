<?php

require __DIR__ . "/components/translator.php";

$site_lang = current_language_code();
$site_dir  = ($site_lang === 'ar') ? 'rtl' : 'ltr';

$template_file = __DIR__ . "/components/template.php";

$template['header']['title'] = t('meta_title', ['brand' => localized_app_config('brand.name', 'Ember & Vine')]);
$template['header']['meta']['description'] = t('meta_description', ['brand' => localized_app_config('brand.name', 'Ember & Vine')]);

ob_start();
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$request_path = rtrim($request_path, '/');
$route = basename($request_path);

$page = ($route === 'order') ? 'order' : 'landing';

include(__DIR__ . "/pages/{$page}.php");
$template['body'] = ob_get_clean();

$template['header']['body'] =
    '<link rel="stylesheet" href="' . htmlspecialchars(client_asset_url('css/main.css'), ENT_QUOTES, 'UTF-8') . '">'
    . '<script>window.I18N = ' . json_encode(site_language_dict(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';

$template['footer'] = '<script type="module" src="' . htmlspecialchars(client_asset_url('js/main.js'), ENT_QUOTES, 'UTF-8') . '"></script>';

include $template_file;

?>
