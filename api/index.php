<?php

// Vercel entry point. The deployment filesystem is read-only except /tmp, so every path Laravel
// writes to (compiled views, cached manifests, logs, uploads) is pointed there.
foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/public'] as $dir) {
    if (! is_dir("/tmp/storage/{$dir}")) {
        mkdir("/tmp/storage/{$dir}", 0755, true);
    }
}

$env = [
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'LOG_CHANNEL' => 'stderr',
];
foreach ($env as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require __DIR__.'/../public/index.php';
