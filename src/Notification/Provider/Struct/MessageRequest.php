<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Struct;

/**
 * What a provider is asked to deliver.
 *
 * A struct rather than a widening parameter list: adding "schedule at" or
 * "message class" later is a new property here, not a signature change rippling
 * through every provider implementation.
 */
class MessageRequest
{
    /**
     * @param string $recipient E.164 digits without a leading "+", as produced by RecipientPhoneResolver
     */
    public function __construct(
        private readonly string $recipient,
        private readonly string $body,
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

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    /**
     * Country calling code, tested as a prefix.
     *
     * E.164 has no ambiguity between a country code and the start of a national
     * number, so a plain prefix test is enough to route.
     */
    public function matchesCountryCode(string $countryCode): bool
    {
        return str_starts_with($this->recipient, $countryCode);
    }
}
