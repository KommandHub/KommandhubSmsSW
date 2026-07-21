<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Fixture;

use Kommandhub\SmsSW\Notification\Provider\NotificationProviderInterface;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * A provider that does what the test tells it to.
 *
 * A real class rather than a mock, so registry, selector and gateway tests
 * exercise the same interface a third-party provider would implement —
 * including the name-keying the registry depends on.
 */
class FakeProvider implements NotificationProviderInterface
{
    public function __construct(
        private readonly string $name,
        private readonly bool $configured = true,
        private readonly bool $supports = true,
        private readonly ?\Throwable $failWith = null,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return ucfirst($this->name);
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->configured;
    }

    public function supports(MessageRequest $request): bool
    {
        return $this->supports;
    }

    public function send(MessageRequest $request): MessageResult
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        return new MessageResult($this->name, $this->name . '-message-id');
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        return $this->configured ? CredentialCheck::valid() : CredentialCheck::invalid('not configured');
    }
}
