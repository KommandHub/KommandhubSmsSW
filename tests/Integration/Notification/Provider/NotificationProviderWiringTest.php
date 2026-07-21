<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Integration\Notification\Provider;

use Kommandhub\SmsSW\Notification\Gateway\RoutingNotificationGateway;
use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use Kommandhub\SmsSW\Notification\Provider\AfricasTalking\AfricasTalkingProvider;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderInterface;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\Termii\TermiiProvider;
use Kommandhub\SmsSW\Notification\Provider\Twilio\TwilioProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * Proves the container half of the architecture, which no unit test can.
 *
 * The registry is populated from a DI tag applied by `_instanceof`, so "a new
 * provider registers itself simply by implementing the contract" is a claim
 * about the compiled container. If that tag stops matching — a moved namespace,
 * an over-broad exclude in the resource glob — every unit test still passes and
 * the plugin silently loses its providers.
 *
 * Requires the Shopware stack: `make up`, then the plugin installed and active.
 */
class NotificationProviderWiringTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEveryProviderReachesTheRegistry(): void
    {
        $registry = $this->getContainer()->get(NotificationProviderRegistry::class);

        static::assertInstanceOf(NotificationProviderRegistry::class, $registry);
        static::assertSame(
            ['termii', 'africasTalking', 'twilio'],
            array_keys($registry->all()),
            'A provider went missing from the sms.provider tag.',
        );
    }

    public function testTheRegistryKeysMatchTheProviderClasses(): void
    {
        $registry = $this->getContainer()->get(NotificationProviderRegistry::class);
        static::assertInstanceOf(NotificationProviderRegistry::class, $registry);

        static::assertInstanceOf(TermiiProvider::class, $registry->get('termii'));
        static::assertInstanceOf(AfricasTalkingProvider::class, $registry->get('africasTalking'));
        static::assertInstanceOf(TwilioProvider::class, $registry->get('twilio'));
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
            $this->getContainer()->get(NotificationGatewayInterface::class),
        );
    }

    public function testProvidersDeclareDistinctNames(): void
    {
        $registry = $this->getContainer()->get(NotificationProviderRegistry::class);
        static::assertInstanceOf(NotificationProviderRegistry::class, $registry);

        $names = array_map(
            static fn (NotificationProviderInterface $provider): string => $provider->getName(),
            array_values($registry->all()),
        );

        static::assertSame($names, array_unique($names), 'Two providers share a name; one silently replaced the other.');
    }

    /**
     * A provider with no credentials must never be offered, or the routing
     * gateway would burn an attempt on a request that cannot be authenticated.
     */
    public function testUnconfiguredProvidersAreNotOffered(): void
    {
        $registry = $this->getContainer()->get(NotificationProviderRegistry::class);
        static::assertInstanceOf(NotificationProviderRegistry::class, $registry);

        foreach ($registry->configured() as $name => $provider) {
            static::assertTrue(
                $provider->isConfigured(),
                sprintf('Provider "%s" was reported as configured but denies it.', $name),
            );
        }
    }
}
