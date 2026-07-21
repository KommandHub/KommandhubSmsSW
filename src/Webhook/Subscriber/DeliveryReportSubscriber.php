<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Subscriber;

use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records what actually happened to a message after the provider accepted it.
 *
 * "Accepted by Termii" and "arrived on the handset" are different facts, and
 * only the delivery report carries the second one. Without this, a merchant
 * asking "did the customer get it?" has nothing to look at.
 *
 * ponytail: logs only, no persistence. Enough to answer the question from
 * var/log; if delivery rates need reporting in the admin, this is where a
 * `notification_send` row gets updated instead.
 */
class DeliveryReportSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [DeliveryReportEvent::class => 'onDeliveryReport'];
    }

    public function onDeliveryReport(DeliveryReportEvent $event): void
    {
        $this->logger->info('Notification delivery report received', [
            'messageId' => $event->getMessageId(),
            'status' => $event->getStatus(),
            'salesChannelId' => $event->getSalesChannelId(),
        ]);
    }
}
