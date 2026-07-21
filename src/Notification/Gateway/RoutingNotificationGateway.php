<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Gateway;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderSelector;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Psr\Log\LoggerInterface;

/**
 * The one gateway the rest of the plugin sees.
 *
 * The queue handler and the test-message service depend on
 * NotificationGatewayInterface and are unaware that providers exist. Adding,
 * removing or reordering providers changes nothing above this line.
 *
 * Failover policy, and why it is asymmetric:
 *
 * - A **transient** failure moves to the next provider. The message is good,
 *   this route is momentarily not, and another route may carry it.
 * - A **permanent** failure stops. The provider understood the request and
 *   refused it; the usual causes — a malformed recipient, a body over the
 *   length limit — would be refused identically everywhere, and the ones that
 *   would not are configuration faults a merchant needs to see rather than have
 *   papered over by a silent fallback that quietly doubles their bill.
 *
 * When every provider fails transiently the last exception is rethrown, so the
 * queue retries the whole ladder rather than treating the message as handled.
 */
class RoutingNotificationGateway implements NotificationGatewayInterface
{
    public function __construct(
        private readonly NotificationProviderSelector $selector,
        private readonly NotificationProviderRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->registry->configured($salesChannelId) !== [];
    }

    public function send(string $recipient, string $body, ?string $salesChannelId = null): ?string
    {
        $request = new MessageRequest($recipient, $body, $salesChannelId);
        $providers = $this->selector->select($request);

        if ($providers === []) {
            throw new PermanentProviderException('No SMS provider is configured for this sales channel.');
        }

        $lastTransient = null;

        foreach ($providers as $provider) {
            try {
                $result = $provider->send($request);

                $this->logger->info('Message accepted by provider', [
                    'provider' => $result->getProviderName(),
                    'messageId' => $result->getMessageId(),
                    'salesChannelId' => $salesChannelId,
                ]);

                return $result->getMessageId();
            } catch (TransientProviderException $exception) {
                $lastTransient = $exception;

                $this->logger->warning('Provider temporarily failed, trying the next one', [
                    'provider' => $provider->getName(),
                    'error' => $exception->getMessage(),
                    'salesChannelId' => $salesChannelId,
                ]);
            }
        }

        // Every route was momentarily unavailable. Surface it so the queue
        // retries rather than recording a send that never happened.
        throw $lastTransient ?? new TransientProviderException('Every provider failed.');
    }
}
