<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider\Twilio;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Notification\Provider\AbstractHttpNotificationProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageResult;

/**
 * Twilio — global fallback.
 *
 * Settings (see config.xml):
 * - `twilioAccountSid`          — account SID, "AC…"
 * - `twilioAuthToken`           — auth token
 * - `twilioFrom`                — sending number in E.164, e.g. "+15005550006"
 * - `twilioMessagingServiceSid` — optional "MG…"; takes precedence over `from`
 *                                 and lets Twilio pick the sender from a pool
 *
 * Declares no country codes on purpose: it is the route of last resort, so it
 * accepts anything and lets the regional providers win where they apply.
 *
 * Quirks contained here: HTTP basic auth, a form-encoded body with capitalised
 * field names, and an account SID embedded in the URL path.
 */
class TwilioProvider extends AbstractHttpNotificationProvider
{
    private const BASE_URL = 'https://api.twilio.com';

    private const API_VERSION = '2010-04-01';

    /**
     * Twilio statuses that mean the message was taken but not yet delivered.
     * Anything else in a 2xx is a message that will never leave.
     */
    private const ACCEPTED_STATUSES = ['queued', 'accepted', 'sending', 'sent', 'scheduled'];

    public function getName(): string
    {
        return 'twilio';
    }

    public function getLabel(): string
    {
        return 'Twilio';
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return $this->setting('accountSid', $salesChannelId) !== ''
            && $this->setting('authToken', $salesChannelId) !== ''
            && $this->sender($salesChannelId) !== [];
    }

    public function send(MessageRequest $request): MessageResult
    {
        $salesChannelId = $request->getSalesChannelId();
        $accountSid = $this->requireSetting('accountSid', $salesChannelId);
        $sender = $this->sender($salesChannelId);

        if ($sender === []) {
            throw new PermanentProviderException('Twilio needs either a sending number or a messaging service SID.');
        }

        $decoded = $this->requestJson(
            'POST',
            sprintf('%s/%s/Accounts/%s/Messages.json', self::BASE_URL, self::API_VERSION, urlencode($accountSid)),
            [
                'auth_basic' => [$accountSid, $this->requireSetting('authToken', $salesChannelId)],
                'body' => [
                    'To' => '+' . $request->getRecipient(),
                    'Body' => $request->getBody(),
                ] + $sender,
            ],
            $salesChannelId,
        );

        $status = $decoded['status'] ?? null;

        // Twilio can return 201 with a status of "failed" when the message was
        // created but immediately rejected downstream.
        if (\is_string($status) && !\in_array($status, self::ACCEPTED_STATUSES, true)) {
            throw new PermanentProviderException(sprintf(
                'Twilio created the message but its status is "%s": %s',
                $status,
                $this->describe($decoded),
            ));
        }

        $sid = $decoded['sid'] ?? null;

        return new MessageResult($this->getName(), \is_string($sid) ? $sid : null, $decoded);
    }

    public function verifyCredentials(?string $salesChannelId = null): CredentialCheck
    {
        $accountSid = $this->setting('accountSid', $salesChannelId);
        $authToken = $this->setting('authToken', $salesChannelId);

        if ($accountSid === '' || $authToken === '') {
            return CredentialCheck::invalid('Twilio needs both an account SID and an auth token.');
        }

        try {
            $decoded = $this->requestJson(
                'GET',
                sprintf('%s/%s/Accounts/%s.json', self::BASE_URL, self::API_VERSION, urlencode($accountSid)),
                ['auth_basic' => [$accountSid, $authToken]],
                $salesChannelId,
            );
        } catch (SmsException $exception) {
            return CredentialCheck::invalid('Twilio rejected these credentials.', $exception->getMessage());
        }

        $status = $decoded['status'] ?? null;

        if ($status === 'suspended' || $status === 'closed') {
            return CredentialCheck::invalid(sprintf('The Twilio account is %s.', (string)$status));
        }

        if ($this->sender($salesChannelId) === []) {
            return CredentialCheck::invalid('Credentials work, but no sending number or messaging service is set.');
        }

        return CredentialCheck::valid('Twilio credentials accepted.');
    }

    /**
     * The sender half of the payload.
     *
     * A messaging service wins over a fixed number: merchants who configure one
     * are opting into Twilio's own sender selection, and sending both is an
     * error on Twilio's side.
     *
     * @return array<string, string> empty when neither is configured
     */
    private function sender(?string $salesChannelId): array
    {
        $messagingServiceSid = $this->setting('messagingServiceSid', $salesChannelId);

        if ($messagingServiceSid !== '') {
            return ['MessagingServiceSid' => $messagingServiceSid];
        }

        $from = $this->setting('from', $salesChannelId);

        return $from !== '' ? ['From' => $from] : [];
    }

    /**
     * @throws PermanentProviderException
     */
    private function requireSetting(string $key, ?string $salesChannelId): string
    {
        $value = $this->setting($key, $salesChannelId);

        if ($value === '') {
            throw new PermanentProviderException(sprintf('Twilio is missing the "%s" setting.', $key));
        }

        return $value;
    }
}
