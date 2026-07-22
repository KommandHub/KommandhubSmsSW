<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * The contract every messaging provider implements.
 *
 * Implementations are collected by their `sms.provider` DI tag — see
 * NotificationProviderRegistry — so adding one is a class plus a config card,
 * with no edit to any existing class.
 *
 * Nothing outside a provider's own directory may name a vendor or know what a
 * sender ID, a carrier route or a messaging service SID is.
 */
interface NotificationProviderInterface
{
    /**
     * Stable machine identifier, e.g. "termii".
     *
     * Persisted in plugin configuration as the merchant's default-provider
     * choice, so changing it is a migration rather than a rename.
     */
    public function getName(): string;

    /**
     * Human label for the administration dropdown.
     */
    public function getLabel(): string;

    /**
     * Whether the merchant has filled in enough configuration for this provider
     * to be usable on this sales channel.
     *
     * Must not perform I/O — it is called for every provider on every send.
     */
    public function isConfigured(?string $salesChannelId = null): bool;

    /**
     * Whether this provider wants to carry this particular message.
     *
     * The routing hook: a provider with direct carrier links in East Africa
     * claims Kenyan and Tanzanian numbers, a global fallback claims everything.
     * Returning false is a preference, not an error — the selector moves on.
     */
    public function supports(MessageRequest $request): bool;

    /**
     * Hands the message to the provider.
     *
     * @throws TransientProviderException on a fault worth retrying
     * @throws PermanentProviderException when the provider understood and refused
     */
    public function send(MessageRequest $request): MessageResult;

    /**
     * Verifies the configured credentials against the provider, for the
     * administration's "test credentials" action.
     *
     * Implementations should use the cheapest authenticated read the API
     * offers. A provider with no such endpoint returns an invalid check
     * explaining that it cannot be verified without sending a message.
     */
    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck;
}
