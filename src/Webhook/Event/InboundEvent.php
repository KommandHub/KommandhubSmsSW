<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

/**
 * A shopper replied to one of the plugin's messages.
 *
 * Nothing consumes this in v1 — it exists so an inbound reply is a typed event
 * a merchant's own subscriber can listen on rather than an ignored callback.
 */
class InboundEvent extends WebhookEvent
{
    public static function getEventName(): string
    {
        return 'inbound';
    }
}
