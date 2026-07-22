<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider;

use Kommandhub\SmsSW\Exception\SmsException;

/**
 * Every messaging provider the container found, keyed by name.
 *
 * Populated from the `sms.provider` tag, so a new provider joins by
 * existing — the autowired resource glob in services.yml tags it through
 * `_instanceof`, and nothing here or in any caller is edited.
 */
class NotificationProviderRegistry
{
    /**
     * @var array<string, NotificationProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<NotificationProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    /**
     * @return array<string, NotificationProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @throws SmsException when no provider answers to that name
     */
    public function get(string $name): NotificationProviderInterface
    {
        return $this->providers[$name]
            ?? throw new SmsException(sprintf('Unknown messaging provider "%s".', $name));
    }

    /**
     * Providers with credentials on this sales channel — the set a send may
     * actually use.
     *
     * @return array<string, NotificationProviderInterface>
     */
    public function configured(?string $salesChannelId = null): array
    {
        return array_filter(
            $this->providers,
            static fn (NotificationProviderInterface $provider): bool => $provider->isConfigured($salesChannelId),
        );
    }
}
