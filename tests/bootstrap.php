<?php

// If running in CI → skip Shopware bootstrap
if (getenv('CI') === 'true') {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';

    if (!file_exists($vendorPath)) {
        // Fallback to Shopware root vendor if plugin vendor is missing (e.g. in local dev with CI=true)
        $vendorPath = __DIR__ . '/../../../../vendor/autoload.php';
    }

    if (file_exists($vendorPath)) {
        require $vendorPath;
    }

    return;
}

// Otherwise (local dev) → use Shopware bootstrap
require __DIR__ . '/TestBootstrapper.php';
require __DIR__ . '/TestBootstrap.php';
