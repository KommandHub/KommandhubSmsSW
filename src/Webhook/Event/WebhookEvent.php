<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for every inbound Notifications webhook.
 *
 * One subclass per provider event type, each declaring NAME. Subscribers listen
 * on the subclass, so adding an event type never touches existing subscribers.
 */
abstract class WebhookEvent extends Event
{
    /**
     * @param array<string, mixed> $payload the decoded webhook body
     */
    public function __construct(
        private readonly array $payload,
        private readonly ?string $salesChannelId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    /**
     * The provider's event-type string, e.g. "charge.success".
     */
    abstract public static function getEventName(): string;
}
