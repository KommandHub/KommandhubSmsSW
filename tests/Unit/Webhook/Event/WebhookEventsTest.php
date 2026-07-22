<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Event;

use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\DndReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookEvent::class)]
#[CoversClass(DeliveryReportEvent::class)]
#[CoversClass(DndReportEvent::class)]
#[CoversClass(InboundEvent::class)]
class WebhookEventsTest extends TestCase
{
    public function testDeliveryReportEvent(): void
    {
        $payload = ['status' => 'DELIVERED', 'message_id' => 'msg_1'];
        $event = new DeliveryReportEvent($payload, 'sc-1');

        $this->assertEquals('outbound', DeliveryReportEvent::getEventName());
        $this->assertEquals('DELIVERED', $event->getStatus());
        $this->assertEquals('msg_1', $event->getMessageId());
        $this->assertEquals($payload, $event->getPayload());
        $this->assertEquals('sc-1', $event->getSalesChannelId());
    }

    public function testDeliveryReportEventWithNonStringPayload(): void
    {
        $event = new DeliveryReportEvent(['status' => 123, 'message_id' => ['id']]);
        $this->assertNull($event->getStatus());
        $this->assertNull($event->getMessageId());
    }

    public function testDeliveryReportEventEmptyPayload(): void
    {
        $event = new DeliveryReportEvent([]);
        $this->assertNull($event->getStatus());
        $this->assertNull($event->getMessageId());
    }

    public function testDndReportEvent(): void
    {
        $this->assertEquals('dnd', DndReportEvent::getEventName());
        $event = new DndReportEvent(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $event->getPayload());
    }

    public function testInboundEvent(): void
    {
        $this->assertEquals('inbound', InboundEvent::getEventName());
        $event = new InboundEvent(['foo' => 'bar']);
        $this->assertEquals(['foo' => 'bar'], $event->getPayload());
    }
}
