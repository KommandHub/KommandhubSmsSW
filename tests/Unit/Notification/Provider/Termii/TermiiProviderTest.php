<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Termii;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Notification\Provider\Termii\TermiiProvider;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class TermiiProviderTest extends ProviderTestCase
{
    private const SETTINGS = [
        'termiiApiKey' => 'key-1',
        'termiiSenderId' => 'Kommandhub',
        'termiiRoute' => 'generic',
    ];

    public function testSendPutsTheApiKeyInTheJsonBody(): void
    {
        $provider = $this->provider($this->client(['message_id' => 'msg-1']));

        $result = $provider->send(new MessageRequest('2348030000000', 'Your order shipped'));

        $this->assertSame('msg-1', $result->getMessageId());
        $this->assertSame('termii', $result->getProviderName());
        $this->assertSame('https://v3.api.termii.com/api/sms/send', $this->capturedUrl);
        $this->assertSame([
            'to' => '2348030000000',
            'from' => 'Kommandhub',
            'sms' => 'Your order shipped',
            'type' => 'plain',
            'channel' => 'generic',
            'api_key' => 'key-1',
        ], $this->sentBody());
    }

    /**
     * Termii's signature failure mode: HTTP 200 carrying a refusal.
     */
    public function testARefusalInsideA200IsPermanent(): void
    {
        $provider = $this->provider($this->client(['message' => 'Invalid Sender ID']));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('Invalid Sender ID');

        $provider->send(new MessageRequest('2348030000000', 'body'));
    }

    public function testAServerErrorIsTransient(): void
    {
        $provider = $this->provider($this->client(['message' => 'upstream down'], 503));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('2348030000000', 'body'));
    }

    public function testRateLimitingIsTransient(): void
    {
        $provider = $this->provider($this->client(['message' => 'slow down'], 429));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('2348030000000', 'body'));
    }

    public function testBadCredentialsArePermanent(): void
    {
        $provider = $this->provider($this->client(['message' => 'Invalid API key'], 401));

        $this->expectException(PermanentProviderException::class);

        $provider->send(new MessageRequest('2348030000000', 'body'));
    }

    public function testAnUnreadableBodyIsTransient(): void
    {
        $provider = $this->provider($this->client('<html>maintenance</html>'));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('2348030000000', 'body'));
    }

    public function testIsConfiguredNeedsBothKeyAndSender(): void
    {
        $client = $this->client([]);

        $this->assertTrue($this->provider($client)->isConfigured());
        $this->assertFalse($this->provider($client, ['termiiApiKey' => 'key-1'])->isConfigured());
        $this->assertFalse($this->provider($client, ['termiiSenderId' => 'Kommandhub'])->isConfigured());
    }

    public function testTheSmsChannelUsesTheConfiguredRoute(): void
    {
        $provider = $this->provider(
            $this->client(['message_id' => 'msg-1']),
            ['termiiApiKey' => 'key-1', 'termiiSenderId' => 'Kommandhub', 'termiiRoute' => 'dnd'],
        );

        $provider->send(new MessageRequest('2348030000000', 'body'));

        $this->assertSame('dnd', $this->sentBody()['channel']);
    }

    /**
     * A value left over from when the route setting also offered "whatsapp"
     * would otherwise send every SMS over WhatsApp.
     */
    public function testAStaleWhatsAppRouteIsIgnoredForSms(): void
    {
        $provider = $this->provider(
            $this->client(['message_id' => 'msg-1']),
            ['termiiApiKey' => 'key-1', 'termiiSenderId' => 'Kommandhub', 'termiiRoute' => 'whatsapp'],
        );

        $provider->send(new MessageRequest('2348030000000', 'body'));

        $this->assertSame('generic', $this->sentBody()['channel']);
    }

    public function testItClaimsWestAfricanDestinationsOnly(): void
    {
        $provider = $this->provider($this->client([]));

        $this->assertTrue($provider->supports(new MessageRequest('2348030000000', 'body')));
        $this->assertFalse($provider->supports(new MessageRequest('15005550006', 'body')));
    }

    public function testTheBaseUrlCanBeOverridden(): void
    {
        $provider = $this->provider(
            $this->client(['message_id' => 'msg-1']),
            self::SETTINGS + ['termiiBaseUrl' => 'https://api.termii.example/'],
        );

        $provider->send(new MessageRequest('2348030000000', 'body'));

        $this->assertSame('https://api.termii.example/api/sms/send', $this->capturedUrl);
    }

    public function testVerifyCredentialsReportsTheBalance(): void
    {
        $provider = $this->provider($this->client(['balance' => 1200]));

        $check = $provider->verifyCredentials();

        $this->assertTrue($check->isValid());
        $this->assertStringContainsString('1200', $check->getMessage());
    }

    public function testVerifyCredentialsRejectsEmptyKey(): void
    {
        $provider = $this->provider($this->client([]), ['termiiApiKey' => '']);
        $this->assertFalse($provider->verifyCredentials()->isValid());
    }

    public function testVerifyCredentialsRejectsMissingBalance(): void
    {
        $provider = $this->provider($this->client(['message' => 'ok']));
        $this->assertFalse($provider->verifyCredentials()->isValid());
    }

    public function testVerifyCredentialsRejectsMissingSender(): void
    {
        $provider = $this->provider($this->client(['balance' => 10]), [
            'termiiApiKey' => 'key',
            'termiiSenderId' => '',
        ]);
        $check = $provider->verifyCredentials();
        $this->assertFalse($check->isValid());
        $this->assertStringContainsString('no sender ID', $check->getMessage());
    }

    public function testSendThrowsWhenSettingMissing(): void
    {
        $provider = $this->provider($this->client([]), [
            'termiiApiKey' => '',
            'termiiSenderId' => 'SENDER',
        ]);
        $this->expectException(PermanentProviderException::class);
        $provider->send(new MessageRequest('234', 'body'));
    }

    public function testVerifyCredentialsReportsRejection(): void
    {
        $provider = $this->provider($this->client(['message' => 'Invalid API key'], 401));

        $check = $provider->verifyCredentials();

        $this->assertFalse($check->isValid());
        $this->assertNotNull($check->getDetail());
    }

    /**
     * @param array<string, string>|null $settings
     */
    public function testGetLabel(): void
    {
        $this->assertSame('Termii', $this->provider($this->client([]))->getLabel());
    }

    public function testSendExposesTheRawProviderResponse(): void
    {
        $decoded = ['message_id' => 'msg-1', 'note' => 'kept for logging'];

        $result = $this->provider($this->client($decoded))->send(new MessageRequest('2348030000000', 'body'));

        $this->assertSame($decoded, $result->getRaw());
    }

    /**
     * A transport-level failure — DNS, TLS, timeout — never reaches the
     * provider and is retryable, so the shared HTTP base turns it into a
     * TransientProviderException.
     */
    public function testATransportFailureIsTransient(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'Connection timed out']));

        $this->expectException(TransientProviderException::class);

        (new TermiiProvider($client, $this->config(self::SETTINGS), new NullLogger()))
            ->send(new MessageRequest('2348030000000', 'body'));
    }

    private function provider(MockHttpClient $client, ?array $settings = null): TermiiProvider
    {
        return new TermiiProvider($client, $this->config($settings ?? self::SETTINGS), new NullLogger());
    }
}
