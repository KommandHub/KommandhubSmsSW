<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\Sendexa;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\Sendexa\SendexaProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class SendexaProviderTest extends ProviderTestCase
{
    private const SETTINGS = [
        'sendexaApiToken' => 'ZGFzaGJvYXJkLXRva2Vu',
        'sendexaSenderId' => 'Kommandhub',
    ];

    /**
     * The documented success envelope: the id sits at data.messageId, not at
     * the top level as with the other providers.
     *
     * @return array<string, mixed>
     */
    private static function accepted(string $messageId = 'msg-1'): array
    {
        return [
            'success' => true,
            'code' => 'OK',
            'message' => 'Message accepted',
            'requestId' => 'req-1',
            'data' => ['messageId' => $messageId, 'status' => 'SENT'],
        ];
    }

    public function testSendPostsJsonWithBasicAuth(): void
    {
        $provider = $this->provider($this->client(self::accepted()));

        $result = $provider->send(new MessageRequest('233200000000', 'Your order shipped'));

        $this->assertSame('msg-1', $result->getMessageId());
        $this->assertSame('sendexa', $result->getProviderName());
        $this->assertSame('https://api.sendexa.co/v1/sms/send', $this->capturedUrl);
        $this->assertSame([
            'to' => '233200000000',
            'from' => 'Kommandhub',
            'message' => 'Your order shipped',
        ], $this->sentBody());
        $this->assertContains('Authorization: Basic ZGFzaGJvYXJkLXRva2Vu', $this->sentHeaders());
    }

    /**
     * The dashboard hands out a ready-made Base64 token, but pasting the raw
     * pair is the obvious mistake — it is what Basic auth normally holds.
     */
    public function testARawCredentialPairIsEncodedRatherThanSentVerbatim(): void
    {
        $provider = $this->provider(
            $this->client(self::accepted()),
            ['sendexaApiToken' => 'client:secret', 'sendexaSenderId' => 'Kommandhub'],
        );

        $provider->send(new MessageRequest('233200000000', 'body'));

        $this->assertContains(
            'Authorization: Basic ' . base64_encode('client:secret'),
            $this->sentHeaders(),
        );
    }

    /**
     * Verified against the live API: Sendexa uses real status codes rather than
     * Termii's refusal-inside-200, so bad credentials are permanent.
     */
    public function testInvalidCredentialsArePermanent(): void
    {
        $provider = $this->provider(
            $this->client(['success' => false, 'message' => 'Invalid API Token or Token Expired'], 401),
        );

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('Invalid API Token or Token Expired');

        $provider->send(new MessageRequest('233200000000', 'body'));
    }

    /**
     * Guards the case the shared envelope allows but the status code does not
     * announce.
     */
    public function testSuccessFalseInsideA2xxIsPermanent(): void
    {
        $provider = $this->provider($this->client(['success' => false, 'message' => 'Sender not approved']));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('Sender not approved');

        $provider->send(new MessageRequest('233200000000', 'body'));
    }

    public function testAServerErrorIsTransient(): void
    {
        $provider = $this->provider($this->client(['success' => false, 'message' => 'upstream'], 503));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('233200000000', 'body'));
    }

    public function testRateLimitingIsTransient(): void
    {
        $provider = $this->provider($this->client(['success' => false, 'message' => 'slow down'], 429));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('233200000000', 'body'));
    }

    /**
     * Caught here rather than spent at the provider: an over-long template is a
     * template bug and the message names the limit.
     */
    public function testAnOverlongBodyIsRefusedBeforeSending(): void
    {
        $provider = $this->provider($this->client(self::accepted()));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('1530');

        $provider->send(new MessageRequest('233200000000', str_repeat('a', 1531)));
    }

    public function testAnOverlongSenderIdIsRefusedBeforeSending(): void
    {
        $provider = $this->provider(
            $this->client(self::accepted()),
            ['sendexaApiToken' => 'token', 'sendexaSenderId' => 'WayTooLongSenderId'],
        );

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('11');

        $provider->send(new MessageRequest('233200000000', 'body'));
    }

    public function testIsConfiguredNeedsBothTokenAndSender(): void
    {
        $client = $this->client([]);

        $this->assertTrue($this->provider($client)->isConfigured());
        $this->assertFalse($this->provider($client, ['sendexaApiToken' => 'token'])->isConfigured());
        $this->assertFalse($this->provider($client, ['sendexaSenderId' => 'Kommandhub'])->isConfigured());
    }

    public function testItClaimsGhanaOnly(): void
    {
        $provider = $this->provider($this->client([]));

        $this->assertTrue($provider->supports(new MessageRequest('233200000000', 'body')));
        $this->assertFalse($provider->supports(new MessageRequest('15005550006', 'body')));
    }


    public function testVerifyCredentialsUsesTheBalanceEndpoint(): void
    {
        $provider = $this->provider($this->client(['success' => true, 'data' => ['balance' => '250.00']]));

        $check = $provider->verifyCredentials();

        $this->assertTrue($check->isValid());
        $this->assertStringContainsString('250.00', $check->getMessage());
        $this->assertSame('https://api.sendexa.co/v1/sms/balance', $this->capturedUrl);
    }

    /**
     * The balance payload is undocumented, so an unrecognised shape must still
     * report working credentials rather than inventing a figure.
     */
    public function testVerifyCredentialsAcceptsAnUnrecognisedBalanceShape(): void
    {
        $provider = $this->provider($this->client(['success' => true, 'data' => ['credits' => 12]]));

        $check = $provider->verifyCredentials();

        $this->assertTrue($check->isValid());
        $this->assertStringNotContainsString('Balance', $check->getMessage());
    }

    public function testVerifyCredentialsReportsRejection(): void
    {
        $provider = $this->provider(
            $this->client(['success' => false, 'message' => 'Invalid API Token or Token Expired'], 401),
        );

        $check = $provider->verifyCredentials();

        $this->assertFalse($check->isValid());
        $this->assertNotNull($check->getDetail());
    }

    /**
     * @param array<string, string>|null $settings
     */
    private function provider(MockHttpClient $client, ?array $settings = null): SendexaProvider
    {
        return new SendexaProvider($client, $this->config($settings ?? self::SETTINGS), new NullLogger());
    }
}
