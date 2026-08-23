<?php
/**
 * Admin view helpers (frontend only).
 * Mirrors the URL helper functions in public/client/components/translator.php
 * but scoped to the /public/admin area.
 */

function app_base_url(): string
{
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script_dir = rtrim($script_dir, '/');

    // Strip the trailing '/public/admin' so the returned base points at the
    // application root (e.g. http://localhost/Portfolio).
    if (substr($script_dir, -13) === '/public/admin') {
        return substr($script_dir, 0, -13) ?: '';
    }

    return $script_dir;
}

function admin_asset_url(string $path): string
{
    return app_base_url() . '/public/admin/assets/' . ltrim($path, '/');
}