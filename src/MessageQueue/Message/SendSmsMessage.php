<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * One message to deliver, already resolved and rendered.
 *
 * Messages are serialised into the queue, so they carry **scalars, not
 * objects**: an entity serialised today may be handled minutes later against
 * changed data, and Shopware entities do not round-trip cleanly.
 *
 * Recipient resolution and template rendering happen at dispatch time, not
 * here. Both need the flow's live order/customer data, and doing them up front
 * means an unusable phone number is a skipped channel inside the flow rather
 * than a message that queues only to die in a worker.
 */
class SendSmsMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $recipient,
        private readonly string $body,
        private readonly string $dedupeKey,
        private readonly ?string $salesChannelId = null,
    ) {
    }


    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Stable across redeliveries of the same logical send, so the handler can
     * tell "the queue gave me this twice" from "the merchant really wants two".
     */
    public function getDedupeKey(): string
    {
        return $this->dedupeKey;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
