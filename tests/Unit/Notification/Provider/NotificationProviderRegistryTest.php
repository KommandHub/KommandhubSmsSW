<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Fixture\FakeProvider;
use PHPUnit\Framework\TestCase;

class NotificationProviderRegistryTest extends TestCase
{
    public function testProvidersAreKeyedByName(): void
    {
        $registry = new NotificationProviderRegistry([new FakeProvider('alpha'), new FakeProvider('beta')]);

        $this->assertSame(['alpha', 'beta'], array_keys($registry->all()));
        $this->assertTrue($registry->has('alpha'));
        $this->assertFalse($registry->has('gamma'));
    }

    public function testGetReturnsTheNamedProvider(): void
    {
        $alpha = new FakeProvider('alpha');
        $registry = new NotificationProviderRegistry([$alpha, new FakeProvider('beta')]);

        $this->assertSame($alpha, $registry->get('alpha'));
    }

    public function testUnknownProviderThrows(): void
    {
        $registry = new NotificationProviderRegistry([new FakeProvider('alpha')]);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Unknown messaging provider "gamma".');

        $registry->get('gamma');
    }

    public function testConfiguredExcludesProvidersWithoutCredentials(): void
    {
        $registry = new NotificationProviderRegistry([
            new FakeProvider('alpha', configured: true),
            new FakeProvider('beta', configured: false),
        ]);

        $this->assertSame(['alpha'], array_keys($registry->configured()));
    }

    public function testEmptyRegistryIsNotAnError(): void
    {
        $registry = new NotificationProviderRegistry([]);

        $this->assertSame([], $registry->all());
        $this->assertSame([], $registry->configured());
    }
}
