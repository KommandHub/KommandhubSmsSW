<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Struct;

/**
 * Outcome of a test send, shaped for the administration to render directly.
 *
 * A failure is an ordinary result rather than an exception: pressing "send
 * test" to discover that a sender ID is unapproved is the feature working, not
 * an error condition. The controller therefore always answers 200 and the
 * administration decides between a green and a red notification.
 *
 * `$reason` is a snippet key so the message is translated in the browser;
 * `$detail` carries the provider's own untranslated wording for support.
 */
class TestMessageResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $reason = null,
        private readonly ?string $detail = null,
        private readonly ?string $messageId = null,
        private readonly ?string $renderedBody = null,
    ) {
    }

    public static function sent(?string $messageId, string $renderedBody): self
    {
        return new self(true, null, null, $messageId, $renderedBody);
    }

    /**
     * @param string $reason snippet key, e.g. "invalidRecipient"
     * @param string|null $detail provider wording, never a credential
     */
    public static function failed(string $reason, ?string $detail = null, ?string $renderedBody = null): self
    {
        return new self(false, $reason, $detail, null, $renderedBody);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * What was actually sent — shown back to the administrator so they can see
     * how the sample variables resolved.
     */
    public function getRenderedBody(): ?string
    {
        return $this->renderedBody;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'success' => $this->success,
            'reason' => $this->reason,
            'detail' => $this->detail,
            'messageId' => $this->messageId,
            'renderedBody' => $this->renderedBody,
        ];
    }
}
