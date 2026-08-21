<?php

function app_config(?string $key = null, mixed $default = null): mixed {
    static $config = null;
    if ($config === null) {
        $file = dirname(__DIR__, 3) . '/config/app.php';
        $loaded = file_exists($file) ? require $file : [];
        $config = is_array($loaded) ? $loaded : [];
    }

    if ($key === null || $key === '') {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function localized_app_config(string $key, mixed $default = null): mixed {
    $lang = current_language_code();
    $localized = app_config('locales.' . $lang . '.' . $key);

    if ($localized !== null) {
        return $localized;
    }

    $english = app_config('locales.en.' . $key);
    if ($english !== null) {
        return $english;
    }

    return app_config($key, $default);
}

function app_brand_html(): string {
    $first = htmlspecialchars((string) localized_app_config('brand.first', 'Ember'), ENT_QUOTES, 'UTF-8');
    $separator = htmlspecialchars((string) localized_app_config('brand.separator', '&'), ENT_QUOTES, 'UTF-8');
    $second = htmlspecialchars((string) localized_app_config('brand.second', 'Vine'), ENT_QUOTES, 'UTF-8');

    return $first . '<em>' . $separator . '</em>' . $second;
}

function app_base_url(): string {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = rtrim($script_dir, '/');

    if (substr($script_dir, -14) === '/public/client') {
        return substr($script_dir, 0, -14) ?: '';
    }

    return $script_dir;
}

function client_base_url(): string {
    return app_base_url() . '/public/client';
}

function app_url(string $path = ''): string {
    $base = app_base_url();
    return ($base ?: '') . '/' . ltrim($path, '/');
}

function localized_app_url(string $path = ''): string {
    return app_url($path) . '?language=' . rawurlencode(current_language_code());
}

function client_asset_url(string $path): string {
    return client_base_url() . '/assets/' . ltrim($path, '/');
}

function app_logo_img(string $class): string {
    $src = (string) app_config('brand.logo.src', './assets/images/logo.svg');
    if (substr($src, 0, 9) === './assets/') {
        $src = client_asset_url(substr($src, 9));
    }

    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars((string) localized_app_config('brand.logo_alt', localized_app_config('brand.plain', 'Ember and Vine logo')), ENT_QUOTES, 'UTF-8');
    $class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

    return '<img class="' . $class . '" src="' . $src . '" alt="' . $alt . '">';
}

function current_language_code(): string {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $lang = $_GET['language'] ?? 'en';
    $lang = is_string($lang) ? strtolower(trim($lang)) : 'en';

    if ($lang != "en"  && $lang != "ar" ) {
        $lang = 'en';
    }

    $cached = $lang;
    return $lang;
}

function site_language_dict(): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $lang = current_language_code();
    $file = __DIR__ . '/../assets/languages/' . $lang . '.json';
    $data = [];

    if (file_exists($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $cached = $data;
    return $data;
}

function t(string $key, array $params = []): string {
    $dict   = site_language_dict();
    $value  = $dict[$key] ?? $key;

    if ($params !== []) {
        foreach ($params as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }
    }

    return $value;
}
