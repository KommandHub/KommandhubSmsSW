<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Subscriber\DeliveryReportSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(DeliveryReportSubscriber::class)]
#[UsesClass(DeliveryReportEvent::class)]
#[UsesClass(WebhookEvent::class)]
class DeliveryReportSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private DeliveryReportSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new DeliveryReportSubscriber($this->logger);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = DeliveryReportSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(DeliveryReportEvent::class, $events);
        $this->assertEquals('onDeliveryReport', $events[DeliveryReportEvent::class]);
    }

    public function testOnDeliveryReportLogsInfo(): void
    {
        $event = new DeliveryReportEvent([
            'message_id' => 'msg_123',
            'status' => 'DELIVERED',
        ], 'sc-123');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Notification delivery report received'),
                $this->callback(function (array $context) {
                    return $context['messageId'] === 'msg_123'
                        && $context['status'] === 'DELIVERED'
                        && $context['salesChannelId'] === 'sc-123';
                })
            );

        $this->subscriber->onDeliveryReport($event);
    }
    public function testOnDeliveryReportLogsInfoWithMissingData(): void
    {
        $event = new DeliveryReportEvent([], null);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('Notification delivery report received'),
                $this->callback(function (array $context) {
                    return $context['messageId'] === null
                        && $context['status'] === null
                        && $context['salesChannelId'] === null;
                })
            );

        $this->subscriber->onDeliveryReport($event);
    }
}
