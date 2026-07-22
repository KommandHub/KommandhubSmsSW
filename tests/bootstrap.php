<?php

// If running in CI → skip Shopware bootstrap
if (getenv('CI') === 'true') {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($vendorPath)) {
        $vendorPath = __DIR__ . '/../vendor/autoload.php';
    }
    require $vendorPath;

    return;
}

// Otherwise (local dev) → use Shopware bootstrap
require __DIR__ . '/TestBootstrapper.php';
require __DIR__ . '/TestBootstrap.php';
