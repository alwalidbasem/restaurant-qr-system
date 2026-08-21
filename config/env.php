<?php

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    throw new Exception('.env file not found');
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {

    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$name, $value] = explode('=', $line, 2);

    $name = trim($name);
    $value = trim($value);

    $value = trim($value, "\"'");

    $_ENV[$name] = $value;
}