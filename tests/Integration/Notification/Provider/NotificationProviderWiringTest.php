<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Integration\Notification\Provider;

use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use Kommandhub\SmsSW\Notification\Gateway\RoutingNotificationGateway;
use Kommandhub\SmsSW\Notification\Provider\AfricasTalking\AfricasTalkingProvider;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderInterface;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\Sendexa\SendexaProvider;
use Kommandhub\SmsSW\Notification\Provider\Termii\TermiiProvider;
use Kommandhub\SmsSW\Notification\Provider\Twilio\TwilioProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\Kernel\KernelFactory;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Kernel;

/**
 * Proves the container half of the architecture, which no unit test can.
 *
 * The registry is populated from a DI tag applied by `_instanceof`, so "a new
 * provider registers itself simply by implementing the contract" is a claim
 * about the compiled container. If that tag stops matching — a moved namespace,
 * an over-broad exclude in the resource glob — every unit test still passes and
 * the plugin silently loses its providers.
 *
 * Why this boots its own kernel instead of using KernelTestBehaviour:
 * KernelTestBehaviour serves Shopware's shared test kernel, which loads no
 * plugins — a static plugin under custom/static-plugins is invisible to it, so
 * `getContainer()` cannot see a single service this plugin defines. The test
 * bootstrapper has already installed and activated the plugin in the test
 * database; a kernel booted with the DbalKernelPluginLoader against that
 * database is the same compiled container the plugin runs in, which is exactly
 * what this test needs to inspect.
 *
 * The subjects — NotificationProviderRegistry and the routing gateway — are
 * marked public in services.yml so they can be fetched from that container;
 * they are internal collaborators with no vendor secret to hide.
 *
 * Requires the Shopware stack and the plugin installed and active in the test
 * database, which `make test` arranges through the bootstrapper.
 */
class NotificationProviderWiringTest extends TestCase
{
    private static ?ContainerInterface $pluginContainer = null;

    private static function container(): ContainerInterface
    {
        if (self::$pluginContainer !== null) {
            return self::$pluginContainer;
        }

        // PROJECT_ROOT is set by the test bootstrapper; requiring the autoloader
        // again returns the already-registered Composer instance.
        $projectRoot = $_SERVER['PROJECT_ROOT'] ?? \dirname(__DIR__, 7);
        $autoloadPath = $projectRoot . '/vendor/autoload.php';

        if (!file_exists($autoloadPath)) {
            static::markTestSkipped('Shopware autoloader not found. Integration tests require a full Shopware installation.');
        }

        $classLoader = require $autoloadPath;

        $kernel = KernelFactory::create(
            environment: 'test',
            debug: false,
            classLoader: $classLoader,
            pluginLoader: new DbalKernelPluginLoader($classLoader, null, Kernel::getConnection()),
        );
        $kernel->boot();

        return self::$pluginContainer = $kernel->getContainer();
    }

    /**
     * Whichever plugin service the caller needs — or a skip.
     *
     * The plugin has to be installed and active in the test database for its
     * services to exist in the container. The test bootstrapper attempts that,
     * but installing a static plugin (one living under custom/static-plugins
     * rather than pulled from Packagist) into the throwaway test database is
     * unreliable across Shopware setups. When it has not happened, the honest
     * outcome is a skip with the fix, not a failure that looks like broken
     * wiring: the wiring itself is exercised the moment the plugin is active,
     * and is additionally verified against the live container during releases.
     *
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $container = self::container();

        if (!$container->has($id)) {
            static::markTestSkipped(sprintf(
                'KommandhubSmsSW is not active in the test database, so "%s" is not in the container. '
                . 'Activate it first: bin/console plugin:install --activate KommandhubSmsSW '
                . '(with DATABASE_URL pointing at the *_test database).',
                $id,
            ));
        }

        $service = $container->get($id);
        static::assertInstanceOf($id, $service);

        return $service;
    }

    private function registry(): NotificationProviderRegistry
    {
        return $this->service(NotificationProviderRegistry::class);
    }

    public function testEveryProviderReachesTheRegistry(): void
    {
        // Sorted comparison: the tag has no explicit priority, so container
        // discovery order is an implementation detail this test should not pin.
        // What matters is the set — every provider present, none lost.
        $names = array_keys($this->registry()->all());
        sort($names);

        static::assertSame(
            ['africasTalking', 'sendexa', 'termii', 'twilio'],
            $names,
            'A provider went missing from the sms.provider tag.',
        );
    }

    public function testTheRegistryKeysMatchTheProviderClasses(): void
    {
        $registry = $this->registry();

        static::assertInstanceOf(TermiiProvider::class, $registry->get('termii'));
        static::assertInstanceOf(AfricasTalkingProvider::class, $registry->get('africasTalking'));
        static::assertInstanceOf(TwilioProvider::class, $registry->get('twilio'));
        static::assertInstanceOf(SendexaProvider::class, $registry->get('sendexa'));
    }

    /**
     * The seam the queue handler and the flow actions depend on. If this alias
     * ever points back at a concrete provider, multi-provider routing is gone
     * while everything still appears to work.
     */
    public function testTheGatewayAliasResolvesToTheRoutingGateway(): void
    {
        static::assertInstanceOf(
            RoutingNotificationGateway::class,
            $this->service(NotificationGatewayInterface::class),
        );
    }

    public function testProvidersDeclareDistinctNames(): void
    {
        $names = array_map(
            static fn (NotificationProviderInterface $provider): string => $provider->getName(),
            array_values($this->registry()->all()),
        );

        static::assertSame($names, array_unique($names), 'Two providers share a name; one silently replaced the other.');
    }

    /**
     * A provider with no credentials must never be offered, or the routing
     * gateway would burn an attempt on a request that cannot be authenticated.
     */
    public function testUnconfiguredProvidersAreNotOffered(): void
    {
        foreach ($this->registry()->configured() as $name => $provider) {
            static::assertTrue(
                $provider->isConfigured(),
                sprintf('Provider "%s" was reported as configured but denies it.', $name),
            );
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$pluginContainer = null;
    }
}
