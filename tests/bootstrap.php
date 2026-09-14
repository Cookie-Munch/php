<?php

declare(strict_types=1);

/**
 * Test bootstrap. Prefers Composer's autoloader when present; otherwise falls
 * back to a minimal PSR-4 autoloader so the plain-PHP test runner (run.php)
 * works with no dependencies installed.
 */

$composer = __DIR__ . '/../vendor/autoload.php';
if (is_file($composer)) {
    require $composer;

    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'CookieMunch\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
