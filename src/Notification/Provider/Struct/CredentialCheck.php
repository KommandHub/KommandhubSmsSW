<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Struct;

/**
 * Result of asking a provider "are these credentials usable?".
 *
 * Deliberately not an exception: the admin "test credentials" button wants to
 * render a red box with a reason, and an unconfigured provider is an ordinary
 * state during setup, not a fault.
 */
class CredentialCheck
{
    private function __construct(
        private readonly bool $valid,
        private readonly string $message,
        private readonly ?string $detail = null,
    ) {
    }

    public static function valid(string $message = 'Credentials accepted'): self
    {
        return new self(true, $message);
    }

    public static function invalid(string $message, ?string $detail = null): self
    {
        return new self(false, $message, $detail);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * The provider's own wording, when it gave one. Never contains credentials.
     */
    public function getDetail(): ?string
    {
        return $this->detail;
    }
}
