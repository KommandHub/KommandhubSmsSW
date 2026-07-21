<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KommandhubSmsSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\SmsSW\\Tests\\', __DIR__);
