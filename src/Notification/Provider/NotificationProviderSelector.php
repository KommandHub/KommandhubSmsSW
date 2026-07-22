<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider;

use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;

/**
 * Decides which providers may carry a message, in order of preference.
 *
 * The ordering rule, highest priority first:
 *
 * 1. the merchant's configured default for this channel, if it is willing;
 * 2. any other configured provider that claims the destination via supports();
 * 3. any other configured provider at all, as a last resort.
 *
 * Rung 2 is the routing requirement — a provider with direct carrier links in
 * its region gets the traffic it is good at. Rung 3 exists because a message
 * that goes out over a merely adequate route beats one that does not go out.
 *
 * Returning a list rather than one provider is what makes failover possible
 * without this class knowing anything about why a send failed.
 */
class NotificationProviderSelector
{
    public function __construct(
        private readonly NotificationProviderRegistry $registry,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, NotificationProviderInterface> ordered by preference, empty when nothing is configured
     */
    public function select(MessageRequest $request): array
    {
        $configured = $this->registry->configured($request->getSalesChannelId());

        if ($configured === []) {
            $this->logger->error('No SMS provider is configured', [
                'salesChannelId' => $request->getSalesChannelId(),
            ]);

            return [];
        }

        $default = $this->config->getString('defaultSmsProvider', $request->getSalesChannelId());

        $preferred = [];
        $willing = [];
        $rest = [];

        foreach ($configured as $name => $provider) {
            if ($name === $default) {
                $preferred[] = $provider;

                continue;
            }

            if ($provider->supports($request)) {
                $willing[] = $provider;

                continue;
            }

            $rest[] = $provider;
        }

        // A default that does not want this destination still goes first: the
        // merchant chose it, and overriding that silently is worse than a
        // slightly suboptimal route.
        return [...$preferred, ...$willing, ...$rest];
    }
}
