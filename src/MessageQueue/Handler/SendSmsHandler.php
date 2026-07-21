<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\MessageQueue\Handler;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\MessageQueue\Message\SendSmsMessage;
use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Hands a rendered message to the gateway.
 *
 * Handlers must be **idempotent**: the queue guarantees at-least-once delivery,
 * so the same message can arrive twice after a worker restart. A duplicate here
 * is not a cosmetic bug — it texts the customer twice and bills the merchant
 * twice — so the dedupe key is claimed before the send, not after.
 *
 * Failure modes are deliberately split: a transport fault throws so the message
 * is retried, while a provider rejection is logged and swallowed, because
 * retrying a message the provider will refuse again just fills the failure
 * transport.
 */
#[AsMessageHandler]
class SendSmsHandler
{
    /**
     * Long enough to cover any realistic retry window, short enough that the
     * cache does not accumulate keys forever.
     */
    private const DEDUPE_TTL = 86400;

    public function __construct(
        private readonly NotificationGatewayInterface $gateway,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendSmsMessage $message): void
    {
        if (!$this->claim($message->getDedupeKey())) {
            $this->logger->info('Skipping notification: already sent', [
                'dedupeKey' => $message->getDedupeKey(),
            ]);

            return;
        }

        try {
            $messageId = $this->gateway->send(
                $message->getRecipient(),
                $message->getBody(),
                $message->getSalesChannelId(),
            );

            $this->logger->info('Notification sent', [
                'messageId' => $messageId,
                'salesChannelId' => $message->getSalesChannelId(),
            ]);
        } catch (TransientProviderException $exception) {
            // Worth another attempt, so give the key back before rethrowing —
            // otherwise the retry would be deduplicated against a send that
            // never happened, and the message would be silently dropped.
            $this->release($message->getDedupeKey());

            throw $exception;
        } catch (SmsException $exception) {
            // The provider answered and refused. Retrying changes nothing.
            $this->logger->error('Notification rejected by provider', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $message->getSalesChannelId(),
            ]);
        }
    }

    /**
     * Claims the key, returning false if someone already had it.
     *
     * ponytail: a cache pool, not a table. The ceiling is that an eviction or a
     * cache flush inside the retry window lets a duplicate through — acceptable
     * for transactional sends, but if exactly-once billing ever matters this
     * becomes a `notification_send` table with a unique index on the key.
     */
    private function claim(string $dedupeKey): bool
    {
        $item = $this->cache->getItem($this->cacheKey($dedupeKey));

        if ($item->isHit()) {
            return false;
        }

        $item->set(true);
        $item->expiresAfter(self::DEDUPE_TTL);
        $this->cache->save($item);

        return true;
    }

    /**
     * Undoes a claim so a retry is allowed to run.
     */
    private function release(string $dedupeKey): void
    {
        $this->cache->deleteItem($this->cacheKey($dedupeKey));
    }

    private function cacheKey(string $dedupeKey): string
    {
        return 'kommandhub_sms_sent_' . hash('xxh128', $dedupeKey);
    }
}
