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

function set_restaurant_website_settings(array $settings): void {
    $GLOBALS['restaurant_website_settings'] = $settings;
}

function restaurant_website_setting(string $baseKey, mixed $default = null): mixed {
    $settings = $GLOBALS['restaurant_website_settings'] ?? [];
    if (!is_array($settings) || $settings === []) {
        return $default;
    }

    $lang = current_language_code();
    $localizedKey = $baseKey . '_' . $lang;
    $englishKey = $baseKey . '_en';

    foreach ([$localizedKey, $englishKey, $baseKey] as $key) {
        if (isset($settings[$key]) && trim((string) $settings[$key]) !== '') {
            return $settings[$key];
        }
    }

    return $default;
}

function restaurant_website_html(string $baseKey, mixed $default = null): string {
    return sanitize_restaurant_html((string) restaurant_website_setting($baseKey, $default));
}

function sanitize_restaurant_html(string $html): string {
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML(
        '<?xml encoding="utf-8"?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $allowedTags = [
        'div' => true,
        'p' => true,
        'br' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        'u' => true,
        'span' => true,
        'small' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'a' => true,
    ];

    $nodes = [];
    foreach ($dom->getElementsByTagName('*') as $node) {
        $nodes[] = $node;
    }

    foreach ($nodes as $node) {
        $tag = strtolower($node->nodeName);
        if (!isset($allowedTags[$tag])) {
            $node->parentNode?->removeChild($node);
            continue;
        }

        if (!$node->hasAttributes()) {
            continue;
        }

        $attributes = [];
        foreach ($node->attributes as $attribute) {
            $attributes[] = $attribute;
        }

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim((string) $attribute->value);
            $isSafeHref = $name === 'href'
                && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/') || str_starts_with($value, '#'));

            if ($tag === 'a' && $isSafeHref) {
                $node->setAttribute('rel', 'noopener noreferrer');
                continue;
            }

            $node->removeAttribute($attribute->name);
        }
    }

    $root = $dom->documentElement;
    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $dom->saveHTML($child);
    }

    return trim($output);
}

function restaurant_website_color(string $key): ?string {
    $settings = $GLOBALS['restaurant_website_settings'] ?? [];
    $value = is_array($settings) ? (string) ($settings[$key] ?? '') : '';

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
}

function restaurant_website_color_token(string $key): ?string {
    $settings = $GLOBALS['restaurant_website_settings'] ?? [];
    $value = is_array($settings) ? trim((string) ($settings[$key] ?? '')) : '';

    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return $value;
    }

    if (preg_match('/^rgba\(\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:0|1|0?\.\d+)\s*\)$/i', $value)) {
        return $value;
    }

    return null;
}

function restaurant_website_css(): string {
    $colors = [
        '--color-bg' => restaurant_website_color('background_color'),
        '--color-bg-alt' => restaurant_website_color('background_alt_color'),
        '--color-surface' => restaurant_website_color('surface_color'),
        '--color-surface-raised' => restaurant_website_color('surface_raised_color'),
        '--color-border' => restaurant_website_color('border_color'),
        '--color-text' => restaurant_website_color('text_color'),
        '--color-text-muted' => restaurant_website_color('text_muted_color'),
        '--color-text-faint' => restaurant_website_color('text_faint_color'),
        '--color-accent' => restaurant_website_color('primary_color'),
        '--color-accent-dark' => restaurant_website_color('accent_dark_color'),
        '--color-accent-soft' => restaurant_website_color_token('accent_soft_color'),
        '--color-ember' => restaurant_website_color('ember_color'),
        '--color-gold' => restaurant_website_color('accent_color'),
        '--color-success' => restaurant_website_color('success_color'),
        '--color-danger' => restaurant_website_color('danger_color'),
    ];

    $settings = $GLOBALS['restaurant_website_settings'] ?? [];
    $websiteColors = [];
    if (is_array($settings) && !empty($settings['website_colors'])) {
        $decoded = json_decode((string) $settings['website_colors'], true);
        $websiteColors = is_array($decoded) ? $decoded : [];
    }

    $css = '';
    foreach ($colors as $variable => $value) {
        if ($value === null) {
            continue;
        }

        $css .= $variable . ':' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ';';
    }

    foreach (restaurant_website_color_variables() as $variable => $_) {
        $value = trim((string) ($websiteColors[$variable] ?? ''));
        if (!restaurant_website_css_color_token_is_safe($value)) {
            continue;
        }

        $css .= $variable . ':' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ';';
    }

    if ($css === '') {
        return '';
    }

    return '<style>:root{' . $css . '}</style>';
}

function restaurant_website_color_variables(): array {
    return [
        '--color-bg' => true,
        '--color-bg-alt' => true,
        '--color-surface' => true,
        '--color-surface-raised' => true,
        '--color-border' => true,
        '--color-text' => true,
        '--color-text-muted' => true,
        '--color-text-faint' => true,
        '--color-accent' => true,
        '--color-accent-dark' => true,
        '--color-accent-soft' => true,
        '--color-ember' => true,
        '--color-gold' => true,
        '--color-success' => true,
        '--color-danger' => true,
        '--color-danger-strong' => true,
        '--color-scrollbar-accent' => true,
        '--color-on-accent' => true,
        '--color-on-success' => true,
        '--color-transparent' => true,
        '--overlay-hero-vertical' => true,
        '--overlay-hero-horizontal' => true,
        '--overlay-status-cover' => true,
        '--overlay-modal-backdrop' => true,
        '--overlay-drawer-backdrop' => true,
        '--overlay-language-backdrop' => true,
        '--overlay-navbar-bg' => true,
        '--overlay-navbar-mobile-bg' => true,
        '--overlay-food-badge-bg' => true,
        '--overlay-panel-bg' => true,
        '--overlay-panel-strong-bg' => true,
        '--overlay-footer-bg' => true,
        '--overlay-footer-strong-bg' => true,
        '--overlay-footer-mobile-bg' => true,
        '--overlay-control-bg' => true,
        '--overlay-control-bg-mid' => true,
        '--overlay-control-bg-focus' => true,
        '--overlay-control-bg-dark' => true,
        '--overlay-control-bg-darker' => true,
        '--overlay-media-bg' => true,
        '--overlay-qty-bg' => true,
        '--overlay-check-inset' => true,
        '--overlay-bill-bg' => true,
        '--overlay-meal-group-bg' => true,
        '--tint-text-045' => true,
        '--tint-text-05' => true,
        '--tint-text-055' => true,
        '--tint-text-06' => true,
        '--tint-text-07' => true,
        '--tint-text-08' => true,
        '--tint-text-09' => true,
        '--tint-text-12' => true,
        '--tint-text-13' => true,
        '--tint-text-14' => true,
        '--tint-text-16' => true,
        '--tint-text-18' => true,
        '--tint-text-20' => true,
        '--tint-text-22' => true,
        '--tint-text-24' => true,
        '--tint-text-28' => true,
        '--tint-text-34' => true,
        '--tint-text-40' => true,
        '--tint-text-50' => true,
        '--tint-text-64' => true,
        '--tint-accent-10' => true,
        '--tint-accent-12' => true,
        '--tint-accent-13' => true,
        '--tint-accent-18' => true,
        '--tint-accent-28' => true,
        '--tint-accent-36' => true,
        '--tint-accent-45' => true,
        '--tint-accent-50' => true,
        '--tint-accent-55' => true,
        '--tint-accent-60' => true,
        '--tint-accent-65' => true,
        '--tint-accent-70' => true,
        '--tint-gold-12' => true,
        '--tint-gold-28' => true,
        '--tint-danger-20' => true,
        '--tint-success-20' => true,
        '--tint-success-48' => true,
        '--glow-success' => true,
        '--shadow-card-hover' => true,
        '--shadow-panel' => true,
        '--shadow-panel-soft' => true,
        '--shadow-panel-strong' => true,
        '--shadow-control' => true,
        '--shadow-toast' => true,
    ];
}

function restaurant_website_css_color_token_is_safe(string $value): bool {
    if ($value === '') {
        return false;
    }

    return preg_match('/^#[0-9a-fA-F]{6}$/', $value)
        || preg_match('/^transparent$/i', $value)
        || preg_match('/^rgba?\(\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\s*,\s*(?:\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value)
        || preg_match('/^linear-gradient\([#a-zA-Z0-9\s,().%-]+\)$/', $value);
}

function app_brand_html(): string {
    $brand = (string) restaurant_website_setting('brand_name', '');
    if ($brand !== '') {
        return sanitize_restaurant_html($brand);
    }

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
    $src = (string) restaurant_website_setting('logo_image_url', '');
    if ($src === '') {
        $src = (string) app_config('brand.logo.src', './assets/images/logo.svg');
    }

    if (substr($src, 0, 9) === './assets/') {
        $src = client_asset_url(substr($src, 9));
    }

    $src = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    $altText = (string) restaurant_website_setting('brand_name', '');
    if ($altText === '') {
        $altText = (string) localized_app_config('brand.logo_alt', localized_app_config('brand.plain', 'Ember and Vine logo'));
    }

    $alt = htmlspecialchars($altText, ENT_QUOTES, 'UTF-8');
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
