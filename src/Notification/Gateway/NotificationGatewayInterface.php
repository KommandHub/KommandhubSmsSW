<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Gateway;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;

/**
 * The seam everything upstream depends on.
 *
 * Callers depend on this and cannot tell which vendor carried a message, how
 * many were tried, or why one was preferred.
 */
interface NotificationGatewayInterface
{
    /**
     * Whether any provider is configured to send.
     *
     * Lets a caller report "nothing is set up" up front instead of inferring it
     * from a send that quietly went nowhere.
     */
    public function isConfigured(?string $salesChannelId = null): bool;

    /**
     * @param string $recipient E.164 digits without a leading "+"
     *
     * @return string|null the provider's message id, when it returned one
     *
     * @throws TransientProviderException when every candidate failed transiently
     * @throws PermanentProviderException on a refusal, or when nothing is configured
     */
    public function send(string $recipient, string $body, ?string $salesChannelId = null): ?string;
}
