<?php

function app_encryption_key(): string
{
    if (isset($_ENV['APP_ENCRYPTION_KEY']) && trim((string) $_ENV['APP_ENCRYPTION_KEY']) !== '') {
        return hash('sha256', (string) $_ENV['APP_ENCRYPTION_KEY'], true);
    }

    $fallback = __DIR__ . '/../../' . basename(__DIR__) . ':' . php_uname('n');

    return hash('sha256', $fallback, true);
}

function encrypt_secret(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', app_encryption_key(), OPENSSL_RAW_DATA, $iv);

    if ($encrypted === false) {
        throw new RuntimeException('Unable to encrypt secret.');
    }

    return base64_encode($iv . $encrypted);
}

function decrypt_secret(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) <= 16) {
        return null;
    }

    $iv = substr($raw, 0, 16);
    $encrypted = substr($raw, 16);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', app_encryption_key(), OPENSSL_RAW_DATA, $iv);

    return $decrypted === false ? null : $decrypted;
}
