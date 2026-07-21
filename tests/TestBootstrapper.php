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
     * Optional fixture loader (disabled by default).
     */
    private function loadFixtures(): void
    {
        $application = new Application($this->getKernel());

        $application->doRun(
            new ArrayInput([
                'command' => 'fixture:load',
                '--group' => $this->fixtureGroup,
                '--env' => 'test',
            ]),
            $this->getOutput()
        );

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
            $args = [
                'command' => 'plugin:install',
                '--activate' => true,
                '--reinstall' => true,
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
