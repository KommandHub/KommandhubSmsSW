<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider;

use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderSelector;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Fixture\FakeProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class NotificationProviderSelectorTest extends TestCase
{
    private Config&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
    }

    public function testTheConfiguredDefaultGoesFirst(): void
    {
        $this->defaultProviderIs('twilio');

        $this->assertSame(
            ['twilio', 'termii'],
            $this->select([new FakeProvider('termii'), new FakeProvider('twilio')]),
        );
    }

    /**
     * The merchant's choice outranks the routing heuristic.
     */
    public function testTheDefaultGoesFirstEvenWhenItDoesNotClaimTheDestination(): void
    {
        $this->defaultProviderIs('twilio');

        $order = $this->select([
            new FakeProvider('termii', supports: true),
            new FakeProvider('twilio', supports: false),
        ]);

        $this->assertSame(['twilio', 'termii'], $order);
    }

    public function testProvidersClaimingTheDestinationOutrankThoseThatDoNot(): void
    {
        $this->defaultProviderIs('');

        $order = $this->select([
            new FakeProvider('twilio', supports: false),
            new FakeProvider('africasTalking', supports: true),
        ]);

        $this->assertSame(['africasTalking', 'twilio'], $order);
    }

    /**
     * A merely adequate route beats no delivery at all.
     */
    public function testUnwillingProvidersRemainAsALastResort(): void
    {
        $this->defaultProviderIs('');

        $this->assertSame(['twilio'], $this->select([new FakeProvider('twilio', supports: false)]));
    }

    public function testUnconfiguredProvidersAreExcluded(): void
    {
        $this->defaultProviderIs('termii');

        $order = $this->select([
            new FakeProvider('termii', configured: false),
            new FakeProvider('twilio', configured: true),
        ]);

        $this->assertSame(['twilio'], $order);
    }

    public function testNothingConfiguredSelectsNothing(): void
    {
        $this->defaultProviderIs('termii');

        $this->assertSame([], $this->select([new FakeProvider('termii', configured: false)]));
    }

    private function defaultProviderIs(string $name): void
    {
        $this->config->method('getString')->willReturn($name);
    }

    /**
     * @param array<int, FakeProvider> $providers
     *
     * @return array<int, string> the selected provider names, in order
     */
    private function select(array $providers): array
    {
        $selector = new NotificationProviderSelector(
            new NotificationProviderRegistry($providers),
            $this->config,
            new NullLogger(),
        );

        return array_map(
            static fn ($provider): string => $provider->getName(),
            $selector->select(new MessageRequest('2348030000000', 'body', 'sales-channel-id')),
        );
    }
}
