<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Main Shopware plugin class for Notifications for Shopware 6.
 *
 * Responsibilities:
 * - Extends the DI container with this plugin's package configuration.
 * - Manages the install / update / activate / deactivate / uninstall lifecycle.
 *
 * Keep this class thin: it is lifecycle glue that can only run with a booted
 * kernel, so it is excluded from the no-kernel coverage gate (see
 * phpunit.dist.xml) and is covered by the @group kernel integration tests.
 */
class KommandhubSmsSW extends Plugin
{
    /**
     * Allow composer commands during plugin execution.
     */
    public function executeComposerCommands(): bool
    {
        return true;
    }

    /**
     * Load additional service configuration files.
     *
     * Shopware loads Resources/config/services.yml on its own; this adds the
     * Resources/config/packages/*.yaml bundle configuration (the monolog
     * channel) on top.
     *
     * @throws \Exception
     *
     * @codeCoverageIgnore
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
        ]);

        $loader = new DelegatingLoader($resolver);

        $configPath = rtrim($this->getPath(), '/') . '/Resources/config';

        $loader->load($configPath . '/{packages}/*.yaml', 'glob');
    }

    /**
     * Plugin installation lifecycle hook.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
    }

    /**
     * Plugin update lifecycle hook.
     *
     * Re-runs the installers so stored identifiers and custom fields are
     * migrated when classes move between versions. Without this, an update (as
     * opposed to a fresh install) can leave a dangling handler identifier.
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);
    }

    /**
     * Plugin activation lifecycle hook.
     */
    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
    }

    /**
     * Plugin deactivation lifecycle hook.
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
    }

    /**
     * Plugin uninstall lifecycle hook.
     *
     * Payment methods are never deleted — that would break historical orders.
     * User data is only removed when the merchant did not ask to keep it.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);

        $connection->executeStatement('DROP TABLE IF EXISTS `kommandhub_sms_template_translation`');
        $connection->executeStatement('DROP TABLE IF EXISTS `kommandhub_sms_template`');
    }

}
