<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Twilio;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Twilio\TwilioProvider;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class TwilioProviderTest extends ProviderTestCase
{
    private const SETTINGS = [
        'twilioAccountSid' => 'AC123',
        'twilioAuthToken' => 'token-1',
        'twilioFrom' => '+15005550006',
    ];

    public function testSendPostsAFormBodyWithCapitalisedFields(): void
    {
        $provider = $this->provider($this->client(['sid' => 'SM1', 'status' => 'queued'], 201));

        $result = $provider->send(new MessageRequest('15551234567', 'Your order shipped'));

        $this->assertSame('SM1', $result->getMessageId());
        $this->assertSame('twilio', $result->getProviderName());
        $this->assertSame('https://api.twilio.com/2010-04-01/Accounts/AC123/Messages.json', $this->capturedUrl);
        $this->assertSame([
            'To' => '+15551234567',
            'Body' => 'Your order shipped',
            'From' => '+15005550006',
        ], $this->sentBody());
    }

    public function testBasicAuthCarriesTheCredentials(): void
    {
        $provider = $this->provider($this->client(['sid' => 'SM1', 'status' => 'queued'], 201));

        $provider->send(new MessageRequest('15551234567', 'body'));

        // The transport folds auth_basic into an Authorization header before the
        // request goes out, so that is what the assertion has to look at.
        $this->assertContains(
            'Authorization: Basic ' . base64_encode('AC123:token-1'),
            $this->sentHeaders(),
        );
    }

    /**
     * A messaging service is an explicit opt-in to Twilio's own sender
     * selection, and sending both fields is an error on Twilio's side.
     */
    public function testAMessagingServiceReplacesTheSendingNumber(): void
    {
        $provider = $this->provider(
            $this->client(['sid' => 'SM1', 'status' => 'queued'], 201),
            self::SETTINGS + ['twilioMessagingServiceSid' => 'MG9'],
        );

        $provider->send(new MessageRequest('15551234567', 'body'));

        $body = $this->sentBody();

        $this->assertSame('MG9', $body['MessagingServiceSid'] ?? null);
        $this->assertArrayNotHasKey('From', $body);
    }

    /**
     * Twilio can create a message and immediately mark it undeliverable.
     */
    public function testAFailedStatusInsideA2xxIsPermanent(): void
    {
        $provider = $this->provider($this->client(['sid' => 'SM1', 'status' => 'failed', 'message' => 'unreachable'], 201));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('failed');

        $provider->send(new MessageRequest('15551234567', 'body'));
    }

    public function testAnInvalidNumberIsPermanent(): void
    {
        $provider = $this->provider($this->client(['code' => 21211, 'message' => 'Invalid To number'], 400));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('Invalid To number');

        $provider->send(new MessageRequest('15551234567', 'body'));
    }

    public function testAServerErrorIsTransient(): void
    {
        $provider = $this->provider($this->client(['message' => 'service unavailable'], 503));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('15551234567', 'body'));
    }

    public function testMissingSenderMeansNotConfigured(): void
    {
        $client = $this->client([]);

        $this->assertTrue($this->provider($client)->isConfigured());
        $this->assertFalse($this->provider($client, [
            'twilioAccountSid' => 'AC123',
            'twilioAuthToken' => 'token-1',
        ])->isConfigured());
    }

    /**
     * The global fallback claims every destination, so the regional providers
     * win on their own turf only by being preferred, never by exclusion.
     */
    public function testItClaimsEveryDestination(): void
    {
        $provider = $this->provider($this->client([]));

        $this->assertTrue($provider->supports(new MessageRequest('15551234567', 'body')));
        $this->assertTrue($provider->supports(new MessageRequest('2348030000000', 'body')));
    }

    public function testVerifyCredentialsAcceptsALiveAccount(): void
    {
        $provider = $this->provider($this->client(['status' => 'active']));

        $this->assertTrue($provider->verifyCredentials()->isValid());
    }

    public function testVerifyCredentialsRejectsASuspendedAccount(): void
    {
        $provider = $this->provider($this->client(['status' => 'suspended']));

        $check = $provider->verifyCredentials();

        $this->assertFalse($check->isValid());
        $this->assertStringContainsString('suspended', $check->getMessage());
    }

    public function testVerifyCredentialsRejectsClosedAccount(): void
    {
        $provider = $this->provider($this->client(['status' => 'closed']));
        $this->assertFalse($provider->verifyCredentials()->isValid());
    }

    public function testVerifyCredentialsRejectsMissingSender(): void
    {
        $provider = $this->provider($this->client(['status' => 'active']), [
            'twilioAccountSid' => 'AC123',
            'twilioAuthToken' => 'token-1',
        ]);
        $check = $provider->verifyCredentials();
        $this->assertFalse($check->isValid());
        $this->assertStringContainsString('no sending number', $check->getMessage());
    }

    public function testVerifyCredentialsRejectsEmptySid(): void
    {
        $provider = $this->provider($this->client([]), [
            'twilioAccountSid' => '',
            'twilioAuthToken' => 'token-1',
        ]);
        $check = $provider->verifyCredentials();
        $this->assertFalse($check->isValid());
    }

    public function testVerifyCredentialsRejectsApiError(): void
    {
        $provider = $this->provider($this->client(['message' => 'Unauthorized'], 401));
        $check = $provider->verifyCredentials();
        $this->assertFalse($check->isValid());
        $this->assertStringContainsString('Unauthorized', $check->getDetail());
    }

    public function testSendThrowsWhenSettingMissingAtRuntime(): void
    {
        $provider = $this->provider($this->client([]), [
            'twilioAccountSid' => '',
            'twilioAuthToken' => 'token-1',
            'twilioFrom' => '+1500',
        ]);

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('missing the "accountSid"');

        $provider->send(new MessageRequest('1555', 'body'));
    }

    public function testSendThrowsWhenSenderMissingAtRuntime(): void
    {
        $provider = $this->provider($this->client([]), [
            'twilioAccountSid' => 'AC123',
            'twilioAuthToken' => 'token-1',
            'twilioFrom' => '',
        ]);

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('needs either a sending number');

        $provider->send(new MessageRequest('1555', 'body'));
    }

    /**
     * @param array<string, string>|null $settings
     */
    public function testGetLabel(): void
    {
        $this->assertSame('Twilio', $this->provider($this->client([]))->getLabel());
    }

    private function provider(MockHttpClient $client, ?array $settings = null): TwilioProvider
    {
        return new TwilioProvider($client, $this->config($settings ?? self::SETTINGS), new NullLogger());
    }
}
