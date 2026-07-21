<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

/**
 * Termii delivery report for a message this plugin sent.
 *
 * The provider discriminates its callbacks with a `type` field rather than an
 * `event` name, and `outbound` is the one carrying delivery status.
 */
class DeliveryReportEvent extends WebhookEvent
{
    public static function getEventName(): string
    {
        return 'outbound';
    }

    /**
     * Provider status, e.g. DELIVERED / Message Failed / Expired / Rejected.
     *
     * Deliberately not mapped onto an enum: the vocabulary belongs to the
     * provider and differs per gateway. Verify the exact strings against
     * current Termii documentation before branching on them.
     */
    public function getStatus(): ?string
    {
        $status = $this->getPayload()['status'] ?? null;

        return \is_string($status) ? $status : null;
    }

    /**
     * Correlates back to the id the sending provider returned. Which provider that
     * was is recorded on the send log line alongside this id.
     */
    public function getMessageId(): ?string
    {
        $messageId = $this->getPayload()['message_id'] ?? null;

        return \is_string($messageId) ? $messageId : null;
    }
}
