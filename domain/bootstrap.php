<?php
declare(strict_types=1);
date_default_timezone_set('Africa/Lagos');
// Local developer settings only. Real web-server environment variables always win.
$envFile = dirname(__DIR__) . '/.env';
if (!is_file($envFile)) $envFile = dirname(__DIR__, 2) . '/.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}
require_once __DIR__ . '/Identity.php';
require_once __DIR__ . '/Rules.php';
require_once __DIR__ . '/PaymentProvider.php';
require_once __DIR__ . '/Lifecycle.php';
