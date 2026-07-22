<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Sendexa;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Notification\Provider\AbstractHttpNotificationProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * Sendexa — Ghana-first messaging.
 *
 * Settings (see config.xml):
 * - `sendexaApiToken` — the Base64 token from the Sendexa dashboard
 * - `sendexaSenderId` — approved sender, at most 11 characters
 * - `sendexaBaseUrl`  — optional override
 *
 * Unlike Termii, Sendexa reports failures with real HTTP status codes and a
 * consistent envelope, so the base class's classification needs no help:
 *
 *     401 {"success":false,"message":"Invalid API Token or Token Expired"}
 *     404 {"success":false,"message":"Not found","error":"…","code":"NOT_FOUND"}
 *
 * Both `message` and `error` are read by describe() already.
 */
class SendexaProvider extends AbstractHttpNotificationProvider
{
    private const DEFAULT_BASE_URL = 'https://api.sendexa.co';

    /**
     * Ghana only, deliberately conservative: Sendexa's published coverage is
     * not documented yet, so this claims the market it is built for. A
     * destination nobody claims still reaches Sendexa through the selector's
     * last resort, so under-claiming costs a preference, not delivery.
     */
    private const COUNTRY_CODES = ['233'];

    /**
     * Sendexa's own cap. Enforced here rather than left to the API so an
     * over-long template fails with a message naming the limit.
     */
    private const MAX_BODY_LENGTH = 1530;

    private const MAX_SENDER_LENGTH = 11;

    public function getName(): string
    {
        return 'sendexa';
    }

    /**
     * The "(beta)" suffix is an honesty marker, not decoration: at the time of
     * writing Sendexa's own docs are partly under construction and no live
     * traffic has been run through this integration, so a merchant choosing it
     * from the admin provider list should see that it is less proven than the
     * others. Drop the suffix once real delivery data backs it — the label is
     * the only thing that changes.
     */
    public function getLabel(): string
    {
        return 'Sendexa (beta)';
    }

    protected function getCountryCodes(): array
    {
        return self::COUNTRY_CODES;
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('apiToken', $salesChannelId) !== ''
            && $this->setting('senderId', $salesChannelId) !== '';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();
        $body = $request->getBody();

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new PermanentProviderException(sprintf(
                'Sendexa accepts at most %d characters; this message is %d.',
                self::MAX_BODY_LENGTH,
                mb_strlen($body),
            ));
        }

        $decoded = $this->requestJson(
            'POST',
            $this->baseUrl($salesChannelId) . '/v1/sms/send',
            [
                'headers' => $this->authHeaders($salesChannelId),
                'json' => [
                    'to' => $request->getRecipient(),
                    'from' => $this->senderId($salesChannelId),
                    'message' => $body,
                ],
            ],
            $salesChannelId,
        );

        // Documented as a shared envelope with an explicit success flag. The
        // base class already turned any non-2xx into an exception, so this
        // guards the case where a 2xx still carries success:false.
        if (($decoded['success'] ?? true) === false) {
            throw new PermanentProviderException(
                sprintf('Sendexa refused the message: %s', $this->describe($decoded)),
            );
        }

        $data = $decoded['data'] ?? null;
        $messageId = \is_array($data) ? ($data['messageId'] ?? null) : null;

        return new MessageResult($this->getName(), \is_string($messageId) ? $messageId : null, $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        if ($this->setting('apiToken', $salesChannelId) === '') {
            return CredentialCheck::invalid('No Sendexa API token configured.');
        }

        try {
            $decoded = $this->requestJson(
                'GET',
                $this->baseUrl($salesChannelId) . '/v1/sms/balance',
                ['headers' => $this->authHeaders($salesChannelId)],
                $salesChannelId,
            );
        } catch (SmsException $exception) {
            return CredentialCheck::invalid('Sendexa rejected these credentials.', $exception->getMessage());
        }

        if ($this->setting('senderId', $salesChannelId) === '') {
            return CredentialCheck::invalid('Token works, but no sender ID is configured.');
        }

        // The balance endpoint's exact payload is undocumented, so report a
        // figure when one is recognisable and stay quiet rather than wrong.
        $data = $decoded['data'] ?? null;
        $balance = \is_array($data) ? ($data['balance'] ?? ($decoded['balance'] ?? null)) : ($decoded['balance'] ?? null);

        return CredentialCheck::valid(
            \is_scalar($balance)
                ? sprintf('Sendexa credentials accepted. Balance: %s', (string)$balance)
                : 'Sendexa credentials accepted.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(?string $salesChannelId): array
    {
        return [
            'Authorization' => 'Basic ' . $this->apiToken($salesChannelId),
            'Accept' => 'application/json',
        ];
    }

    /**
     * The dashboard hands out a ready-made Base64 token, which is what the
     * setting expects verbatim.
     *
     * A merchant who pastes the raw `id:secret` pair instead — an easy mistake,
     * since that is what Basic auth usually holds — would otherwise get an
     * opaque 401. Encoding it for them costs one branch and removes a support
     * ticket. A value containing a colon cannot be Base64, so the test is safe.
     */
    private function apiToken(?string $salesChannelId): string
    {
        $token = $this->setting('apiToken', $salesChannelId);

        return str_contains($token, ':') ? base64_encode($token) : $token;
    }

    /**
     * @throws PermanentProviderException
     */
    private function senderId(?string $salesChannelId): string
    {
        $senderId = $this->setting('senderId', $salesChannelId);

        if ($senderId === '') {
            throw new PermanentProviderException('Sendexa is missing the "senderId" setting.');
        }

        if (mb_strlen($senderId) > self::MAX_SENDER_LENGTH) {
            throw new PermanentProviderException(sprintf(
                'Sendexa sender IDs are at most %d characters; "%s" is %d.',
                self::MAX_SENDER_LENGTH,
                $senderId,
                mb_strlen($senderId),
            ));
        }

        return $senderId;
    }

    private function baseUrl(?string $salesChannelId): string
    {
        return rtrim($this->setting('baseUrl', $salesChannelId) ?: self::DEFAULT_BASE_URL, '/');
    }
}
