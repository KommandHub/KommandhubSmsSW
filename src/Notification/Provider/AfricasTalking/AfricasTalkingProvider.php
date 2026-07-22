<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\AfricasTalking;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Notification\Provider\AbstractHttpNotificationProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * Africa's Talking — East Africa, direct carrier routing.
 *
 * Settings (see config.xml):
 * - `africasTalkingUsername` — account username; "sandbox" selects the sandbox
 * - `africasTalkingApiKey`   — API key from the dashboard
 * - `africasTalkingSenderId` — optional approved alphanumeric sender or short
 *                              code; omitted means the shared pool
 *
 * Three quirks, all contained here: the key travels in an `apiKey` header, the
 * body is form-encoded rather than JSON, and recipients must carry a leading
 * "+" — the rest of the plugin works in bare E.164 digits.
 *
 * A per-recipient status is nested in the response, so unlike a plain HTTP
 * error a rejection can arrive inside a 201.
 */
class AfricasTalkingProvider extends AbstractHttpNotificationProvider
{
    private const BASE_URL_LIVE = 'https://api.africastalking.com';

    private const BASE_URL_SANDBOX = 'https://api.sandbox.africastalking.com';

    /**
     * The username Africa's Talking reserves for its sandbox.
     */
    private const SANDBOX_USERNAME = 'sandbox';

    /**
     * Kenya, Uganda, Tanzania, Rwanda, Malawi, Ethiopia, Nigeria.
     */
    private const COUNTRY_CODES = ['254', '256', '255', '250', '265', '251', '234'];

    /**
     * Status codes Africa's Talking uses for an accepted message.
     *
     * 100 Processed, 101 Sent, 102 Queued. Anything else is a refusal, and the
     * accompanying `status` string carries the reason.
     */
    private const ACCEPTED_STATUS_CODES = [100, 101, 102];

    public function getName(): string
    {
        return 'africasTalking';
    }

    public function getLabel(): string
    {
        return "Africa's Talking";
    }

    protected function getCountryCodes(): array
    {
        return self::COUNTRY_CODES;
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('apiKey', $salesChannelId) !== ''
            && $this->setting('username', $salesChannelId) !== '';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();
        $username = $this->requireSetting('username', $salesChannelId);

        $payload = [
            'username' => $username,
            'to' => '+' . $request->getRecipient(),
            'message' => $request->getBody(),
        ];

        $senderId = $this->setting('senderId', $salesChannelId);

        if ($senderId !== '') {
            $payload['from'] = $senderId;
        }

        $decoded = $this->requestJson(
            'POST',
            $this->baseUrl($salesChannelId) . '/version1/messaging',
            [
                'headers' => [
                    'apiKey' => $this->requireSetting('apiKey', $salesChannelId),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                // Symfony form-encodes an array body, which is what the API wants.
                'body' => $payload,
            ],
            $salesChannelId,
        );

        return new MessageResult($this->getName(), $this->extractMessageId($decoded), $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        $username = $this->setting('username', $salesChannelId);
        $apiKey = $this->setting('apiKey', $salesChannelId);

        if ($username === '' || $apiKey === '') {
            return CredentialCheck::invalid("Africa's Talking needs both a username and an API key.");
        }

        try {
            $decoded = $this->requestJson(
                'GET',
                $this->baseUrl($salesChannelId) . '/version1/user',
                [
                    'headers' => ['apiKey' => $apiKey, 'Accept' => 'application/json'],
                    'query' => ['username' => $username],
                ],
                $salesChannelId,
            );
        } catch (SmsException $exception) {
            return CredentialCheck::invalid("Africa's Talking rejected these credentials.", $exception->getMessage());
        }

        $userData = $decoded['UserData'] ?? null;
        $balance = \is_array($userData) ? ($userData['balance'] ?? null) : null;

        if (!\is_string($balance)) {
            return CredentialCheck::invalid("Africa's Talking did not return account data.", $this->describe($decoded));
        }

        return CredentialCheck::valid(sprintf("Africa's Talking credentials accepted. Balance: %s", $balance));
    }

    /**
     * Digs the per-recipient outcome out of the envelope and fails loudly on a
     * refusal that arrived inside a 2xx.
     *
     * @param array<string, mixed> $decoded
     *
     * @throws PermanentProviderException
     */
    private function extractMessageId(array $decoded): ?string
    {
        $smsMessageData = $decoded['SMSMessageData'] ?? null;
        $recipients = \is_array($smsMessageData) ? ($smsMessageData['Recipients'] ?? []) : [];

        if (!\is_array($recipients) || $recipients === []) {
            // No recipient entry at all means nothing was queued; the envelope
            // message explains why (commonly an unrecognised sender ID).
            $message = \is_array($smsMessageData) ? ($smsMessageData['Message'] ?? null) : null;

            throw new PermanentProviderException(sprintf(
                "Africa's Talking accepted no recipients: %s",
                \is_string($message) ? $message : 'no reason given',
            ));
        }

        $recipient = $recipients[0];

        if (!\is_array($recipient)) {
            throw new PermanentProviderException("Africa's Talking returned an invalid recipient entry.");
        }

        $statusCode = $recipient['statusCode'] ?? null;

        if (!\in_array($statusCode, self::ACCEPTED_STATUS_CODES, true)) {
            $status = \is_string($recipient['status'] ?? null)
                ? $recipient['status']
                : 'no reason given';

            throw new PermanentProviderException(sprintf(
                "Africa's Talking refused the recipient (status %s): %s",
                \is_scalar($statusCode) ? (string)$statusCode : 'unknown',
                $status,
            ));
        }

        $messageId = $recipient['messageId'] ?? null;

        return \is_string($messageId) ? $messageId : null;
    }

    /**
     * The sandbox is selected by username, not by a separate toggle — that is
     * how Africa's Talking models it, and a second switch would let the two
     * disagree.
     */
    private function baseUrl(?string $salesChannelId): string
    {
        return $this->setting('username', $salesChannelId) === self::SANDBOX_USERNAME
            ? self::BASE_URL_SANDBOX
            : self::BASE_URL_LIVE;
    }

    /**
     * @throws PermanentProviderException
     */
    private function requireSetting(string $key, ?string $salesChannelId): string
    {
        $value = $this->setting($key, $salesChannelId);

        if ($value === '') {
            throw new PermanentProviderException(sprintf('Africa\'s Talking is missing the "%s" setting.', $key));
        }

        return $value;
    }
}
