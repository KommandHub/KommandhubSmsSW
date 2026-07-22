<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Service;

use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\DndReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use Kommandhub\SmsSW\Webhook\Service\WebhookEventFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookEventFactory::class)]
#[UsesClass(WebhookEvent::class)]
class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WebhookEventFactory();
    }

    public function testCreateDeliveryReport(): void
    {
        $event = $this->factory->create('outbound', ['id' => 1], 'sc-1');
        $this->assertInstanceOf(DeliveryReportEvent::class, $event);
        $this->assertEquals(['id' => 1], $event->getPayload());
        $this->assertEquals('sc-1', $event->getSalesChannelId());
    }

    public function testCreateInbound(): void
    {
        $event = $this->factory->create('inbound', ['text' => 'hi']);
        $this->assertInstanceOf(InboundEvent::class, $event);
    }

    public function testCreateDnd(): void
    {
        $event = $this->factory->create('dnd', ['phone' => '123']);
        $this->assertInstanceOf(DndReportEvent::class, $event);
    }

    public function testCreateUnknownReturnsNull(): void
    {
        $event = $this->factory->create('unknown', []);
        $this->assertNull($event);
    }
}
