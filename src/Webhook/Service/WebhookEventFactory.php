<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Service;

use Kommandhub\SmsSW\Webhook\Event\DeliveryReportEvent;
use Kommandhub\SmsSW\Webhook\Event\DndReportEvent;
use Kommandhub\SmsSW\Webhook\Event\InboundEvent;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;

/**
 * Maps a provider event-type string onto a typed event object.
 *
 * Unknown types return null rather than throwing: providers add event types
 * over time, and a webhook this plugin does not care about must still be
 * answered with 200 or the provider will retry it forever.
 */
class WebhookEventFactory
{
    /**
     * @var array<string, class-string<WebhookEvent>>
     */
    private const EVENT_MAP = [
        'outbound' => DeliveryReportEvent::class,
        'inbound' => InboundEvent::class,
        'dnd' => DndReportEvent::class,
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public function create(string $eventType, array $payload, ?string $salesChannelId = null): ?WebhookEvent
    {
        $class = self::EVENT_MAP[$eventType] ?? null;

        if ($class === null) {
            return null;
        }

        return new $class($payload, $salesChannelId);
    }
}
