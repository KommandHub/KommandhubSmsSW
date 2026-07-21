<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Gateway;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Gateway\RoutingNotificationGateway;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderSelector;
use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Fixture\FakeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RoutingNotificationGatewayTest extends TestCase
{
    public function testTheFirstWorkingProviderWins(): void
    {
        $gateway = $this->gateway([new FakeProvider('termii'), new FakeProvider('twilio')], default: 'termii');

        $this->assertSame('termii-message-id', $gateway->send('2348030000000', 'body'));
    }

    /**
     * A transient failure is this route being momentarily unavailable, not the
     * message being bad — so the next provider gets a turn.
     */
    public function testATransientFailureFallsThroughToTheNextProvider(): void
    {
        $gateway = $this->gateway([
            new FakeProvider('termii', failWith: new TransientProviderException('timeout')),
            new FakeProvider('twilio'),
        ], default: 'termii');

        $this->assertSame('twilio-message-id', $gateway->send('2348030000000', 'body'));
    }

    /**
     * A permanent refusal would be repeated by every other provider, and
     * retrying it elsewhere risks paying twice for the same message.
     */
    public function testAPermanentFailureStopsTheLadder(): void
    {
        $gateway = $this->gateway([
            new FakeProvider('termii', failWith: new PermanentProviderException('invalid recipient')),
            new FakeProvider('twilio'),
        ], default: 'termii');

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('invalid recipient');

        $gateway->send('2348030000000', 'body');
    }

    public function testEveryProviderFailingTransientlyRethrows(): void
    {
        $gateway = $this->gateway([
            new FakeProvider('termii', failWith: new TransientProviderException('timeout')),
            new FakeProvider('twilio', failWith: new TransientProviderException('503')),
        ], default: 'termii');

        $this->expectException(TransientProviderException::class);

        $gateway->send('2348030000000', 'body');
    }


    public function testIsConfiguredReflectsWhetherAnyProviderHasCredentials(): void
    {
        $this->assertTrue($this->gateway([new FakeProvider('termii')], default: 'termii')->isConfigured());

        $this->assertFalse(
            $this->gateway([new FakeProvider('termii', configured: false)], default: 'termii')->isConfigured(),
        );
    }

    /**
     * @param array<int, FakeProvider> $providers
     */
    private function gateway(array $providers, string $default): RoutingNotificationGateway
    {
        $config = $this->createMock(Config::class);
        $config->method('getString')->willReturn($default);

        $registry = new NotificationProviderRegistry($providers);

        return new RoutingNotificationGateway(
            new NotificationProviderSelector($registry, $config, new NullLogger()),
            $registry,
            new NullLogger(),
        );
    }
}
