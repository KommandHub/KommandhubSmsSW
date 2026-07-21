<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Struct;

/**
 * The outcome of an accepted send, whichever channel carried it.
 *
 * "Accepted" is all any provider can tell us synchronously — whether the
 * handset received it arrives later on the delivery webhook, correlated by
 * message id. `$providerName` is carried so that correlation knows which
 * provider's id it is holding.
 */
class MessageResult
{
    /**
     * @param array<string, mixed> $raw the decoded provider response, for logging and debugging
     */
    public function __construct(
        private readonly string $providerName,
        private readonly ?string $messageId,
        private readonly array $raw = [],
    ) {
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRaw(): array
    {
        return $this->raw;
    }
}
