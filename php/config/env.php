<?php

/**
 * Lightweight .env loader for local XAMPP development.
 */
function loadEnv($path = null)
{
    $path = $path ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        $existingValue = getenv($key);

        if ($key !== '' && ($existingValue === false || $existingValue === '')) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnv();
