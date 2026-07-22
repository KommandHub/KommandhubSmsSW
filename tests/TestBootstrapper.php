<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;
use Shopware\Core\TestBootstrapper as ShopwareTestBootstrapper;

class TestBootstrapper extends ShopwareTestBootstrapper
{
    private bool $forceInstallPlugins = false;

    private bool $loadEnvFile = true;

    private bool $commercialEnabled = false;

    /**
     * @var array<string>
     */
    private array $activePlugins = [];

    private string $fixtureGroup = 'notifications';

    protected bool $forceLoadFixtures = false;

    /**
     * Optional fixture loading hook (kept for extensibility).
     */
    public function setFixtureGroup(string $fixtureGroup): void
    {
        $this->fixtureGroup = $fixtureGroup;
    }

    /**
     * Optional fixture loading hook (kept for extensibility).
     */
    public function setForceLoadFixtures(bool $forceLoadFixtures): void
    {
        $this->forceLoadFixtures = $forceLoadFixtures;
    }

    public function bootstrap(): ShopwareTestBootstrapper
    {
        $_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $this->getProjectDir();

        if (!\defined('TEST_PROJECT_DIR')) {
            \define('TEST_PROJECT_DIR', $_SERVER['PROJECT_ROOT']);
        }

        if ($this->commercialEnabled && $this->getPluginPath('SwagCommercial')) {
            $this->addActivePlugins('SwagCommercial');
        }

        $classLoader = $this->getClassLoader();

        if ($this->loadEnvFile) {
            $this->loadEnvFile();
        }

        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->getDatabaseUrl();

        KernelLifecycleManager::prepare($classLoader);

        if ($this->isForceInstall() || !$this->dbExists()) {
            $this->install();

            if ($this->activePlugins !== []) {
                $this->installPlugins();
            }

            $this->loadFixtures();
        } elseif ($this->forceInstallPlugins) {
            $this->installPlugins();
        }

        if ($this->forceLoadFixtures && $this->dbExists()) {
            $this->loadFixtures();
        }

        return $this;
    }

    /**
     * Optional fixture loader.
     *
     * The plugin ships no fixtures; `fixture:load` comes from a separate dev
     * bundle that is not installed here, so calling it unconditionally aborted
     * every fresh-DB test run with "There are no commands defined in the
     * 'fixture' namespace". The command's absence is the normal state, not an
     * error — skip when it is missing so the hook stays available for an install
     * that does provide fixtures without breaking one that does not.
     */
    private function loadFixtures(): void
    {
        $application = new Application($this->getKernel());
        $application->setAutoExit(false);

        // Only run the fixture command when it exists; the rest of this method
        // is not about fixtures.
        if ($application->has('fixture:load')) {
            $application->doRun(
                new ArrayInput([
                    'command' => 'fixture:load',
                    '--group' => $this->fixtureGroup,
                    '--env' => 'test',
                ]),
                $this->getOutput()
            );
        }

        // Reboot unconditionally: this runs right after the plugin is installed
        // and activated in the DB, and the fresh kernel is what actually loads
        // the plugin's services into the container. Skipping it — as an early
        // return for the missing fixture command did — leaves integration tests
        // looking at a container compiled before the plugin existed, so every
        // plugin service reads as "not found".
        KernelLifecycleManager::bootKernel();
    }

    private function getKernel(): KernelInterface
    {
        return KernelLifecycleManager::getKernel();
    }

    private function getKernelContainer(): ContainerInterface
    {
        return $this->getKernel()->getContainer();
    }

    private function dbExists(): bool
    {
        try {
            $connection = $this->getKernelContainer()->get(Connection::class);
            $connection->executeQuery('SELECT 1 FROM `plugin`')->fetchAllAssociative();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function loadEnvFile(): void
    {
        if (!class_exists(Dotenv::class)) {
            throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
        }

        $envFilePath = $this->getProjectDir() . '/.env';

        if (\is_file($envFilePath) || \is_file($envFilePath . '.dist') || \is_file($envFilePath . '.local.php')) {
            (new Dotenv())->usePutenv()->bootEnv($envFilePath);
        }
    }

    private function install(): void
    {
        $application = new Application($this->getKernel());

        $returnCode = $application->doRun(
            new ArrayInput(
                [
                    'command' => 'system:install',
                    '--create-database' => true,
                    '--force' => true,
                    '--drop-database' => true,
                    '--basic-setup' => true,
                    '--no-assign-theme' => true,
                ]
            ),
            $this->getOutput()
        );

        if ($returnCode !== Command::SUCCESS) {
            throw new \RuntimeException('system:install failed');
        }

        // create new kernel after install
        KernelLifecycleManager::bootKernel(false);
    }

    private function installPlugins(): void
    {
        $application = new Application($this->getKernel());
        $application->doRun(new ArrayInput(['command' => 'plugin:refresh']), $this->getOutput());

        $kernel = KernelLifecycleManager::bootKernel();

        $application = new Application($kernel);

        foreach ($this->activePlugins as $activePlugin) {
            // No --reinstall: this path only runs against a freshly created test
            // database where the plugin has never been installed, so --reinstall
            // means "uninstall then install", and the uninstall of a
            // not-installed plugin aborts the install — leaving the plugin
            // registered but inactive, so its services never load and every
            // integration test reads as "service not found".
            $args = [
                'command' => 'plugin:install',
                '--activate' => true,
                'plugins' => [$activePlugin],
            ];

            $returnCode = $application->doRun(new ArrayInput($args), $this->getOutput());

            if ($returnCode !== Command::SUCCESS) {
                throw new \RuntimeException('system:install failed');
            }
        }

        KernelLifecycleManager::bootKernel();
    }
}
