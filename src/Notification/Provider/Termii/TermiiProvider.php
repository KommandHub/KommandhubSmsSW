<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Termii;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Notification\Provider\AbstractHttpNotificationProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * Termii — West Africa, Nigeria-first, direct carrier routing.
 *
 * Settings (see config.xml):
 * - `termiiApiKey`   — API key from the Termii dashboard
 * - `termiiSenderId` — approved alphanumeric sender
 * - `termiiRoute`    — carrier route for SMS: "generic" or "dnd"
 * - `termiiBaseUrl`  — optional override; Termii has region-specific hosts
 *
 * Termii calls its carrier route a "channel", which is not what this plugin
 * means by the word — hence the setting name `termiiRoute`. Values are
 * "generic" and "dnd"; the latter reaches Nigerian numbers on the
 * do-not-disturb list at a different price.
 *
 * Two Termii quirks, both contained in this class: the API key travels in the
 * JSON body rather than a header, and a refusal is reported as HTTP 200 with an
 * error body and no message id.
 */
class TermiiProvider extends AbstractHttpNotificationProvider
{
    private const DEFAULT_BASE_URL = 'https://v3.api.termii.com';

    /**
     * Nigeria, Ghana, Côte d'Ivoire, Senegal, Kenya — the markets Termii routes
     * directly. Anything else still works, it is simply not preferred.
     */
    private const COUNTRY_CODES = ['234', '233', '225', '221', '254'];

    private const ROUTE_DEFAULT = 'generic';

    public function getName(): string
    {
        return 'termii';
    }

    public function getLabel(): string
    {
        return 'Termii';
    }

    protected function getCountryCodes(): array
    {
        return self::COUNTRY_CODES;
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('apiKey', $salesChannelId) !== ''
            && $this->setting('senderId', $salesChannelId) !== '';
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();

        $decoded = $this->requestJson('POST', $this->baseUrl($salesChannelId) . '/api/sms/send', [
            'json' => [
                'to' => $request->getRecipient(),
                'from' => $this->requireSetting('senderId', $salesChannelId),
                'sms' => $request->getBody(),
                'type' => 'plain',
                'channel' => $this->route($request->getSalesChannelId()),
                'api_key' => $this->requireSetting('apiKey', $salesChannelId),
            ],
        ], $salesChannelId);

        $messageId = $decoded['message_id'] ?? null;

        // Termii answers 200 even when it refuses, so the status code alone
        // does not tell us whether anything was sent — the message id does.
        if (!\is_string($messageId) || $messageId === '') {
            throw new PermanentProviderException(sprintf('Termii refused the message: %s', $this->describe($decoded)));
        }

        return new MessageResult($this->getName(), $messageId, $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        if ($this->setting('apiKey', $salesChannelId) === '') {
            return CredentialCheck::invalid('No Termii API key configured.');
        }

        try {
            $decoded = $this->requestJson('GET', $this->baseUrl($salesChannelId) . '/api/get-balance', [
                'query' => ['api_key' => $this->setting('apiKey', $salesChannelId)],
            ], $salesChannelId);
        } catch (SmsException $exception) {
            return CredentialCheck::invalid('Termii rejected these credentials.', $exception->getMessage());
        }

        if (!isset($decoded['balance'])) {
            return CredentialCheck::invalid('Termii did not return a balance.', $this->describe($decoded));
        }

        $balance = $decoded['balance'];

        if ($this->setting('senderId', $salesChannelId) === '') {
            return CredentialCheck::invalid('API key works, but no sender ID is configured.');
        }

        return CredentialCheck::valid(sprintf('Termii credentials accepted. Balance: %s', \is_scalar($balance) ? (string)$balance : 'unknown'));
    }

    /**
     * The carrier route Termii should use.
     *
     * A stale "whatsapp" from when this plugin still offered that channel would
     * route every SMS over WhatsApp, so it is ignored rather than trusted.
     * The config carry-over migration drops it too; this is the second guard.
     */
    private function route(?string $salesChannelId): string
    {
        $configured = $this->setting('route', $salesChannelId);

        return $configured === '' || $configured === 'whatsapp' ? self::ROUTE_DEFAULT : $configured;
    }

    private function baseUrl(?string $salesChannelId): string
    {
        return rtrim($this->setting('baseUrl', $salesChannelId) ?: self::DEFAULT_BASE_URL, '/');
    }

    /**
     * @throws PermanentProviderException
     */
    private function requireSetting(string $key, ?string $salesChannelId): string
    {
        $value = $this->setting($key, $salesChannelId);

        if ($value === '') {
            throw new PermanentProviderException(sprintf('Termii is missing the "%s" setting.', $key));
        }

        return $value;
    }
}
