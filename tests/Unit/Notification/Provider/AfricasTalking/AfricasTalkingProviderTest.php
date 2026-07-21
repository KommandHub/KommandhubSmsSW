<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider\AfricasTalking;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\AfricasTalking\AfricasTalkingProvider;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Tests\Unit\Notification\Provider\ProviderTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class AfricasTalkingProviderTest extends ProviderTestCase
{
    private const SETTINGS = [
        'africasTalkingUsername' => 'kommandhub',
        'africasTalkingApiKey' => 'atsk-1',
        'africasTalkingSenderId' => 'KHUB',
    ];

    /**
     * @param array<int, array<string, mixed>> $recipients
     *
     * @return array<string, mixed>
     */
    private static function envelope(array $recipients, string $message = 'Sent to 1/1'): array
    {
        return ['SMSMessageData' => ['Message' => $message, 'Recipients' => $recipients]];
    }

    public function testSendUsesTheApiKeyHeaderAndAFormBody(): void
    {
        $provider = $this->provider($this->client(self::envelope([
            ['statusCode' => 101, 'status' => 'Success', 'messageId' => 'ATXid_1'],
        ])));

        $result = $provider->send(new MessageRequest('254700000000', 'Your order shipped'));

        $this->assertSame('ATXid_1', $result->getMessageId());
        $this->assertSame('africasTalking', $result->getProviderName());
        $this->assertSame('https://api.africastalking.com/version1/messaging', $this->capturedUrl);
        $this->assertContains('apiKey: atsk-1', $this->sentHeaders());
        $this->assertSame([
            'username' => 'kommandhub',
            'to' => '+254700000000',
            'message' => 'Your order shipped',
            'from' => 'KHUB',
        ], $this->sentBody());
    }

    /**
     * The rest of the plugin works in bare digits; only this provider needs the
     * plus, so only this provider adds it.
     */
    public function testTheRecipientGainsALeadingPlus(): void
    {
        $provider = $this->provider($this->client(self::envelope([
            ['statusCode' => 101, 'status' => 'Success', 'messageId' => 'ATXid_1'],
        ])));

        $provider->send(new MessageRequest('254700000000', 'body'));

        $this->assertSame('+254700000000', $this->sentBody()['to']);
    }

    public function testTheSenderIdIsOmittedWhenNotConfigured(): void
    {
        $provider = $this->provider(
            $this->client(self::envelope([['statusCode' => 101, 'status' => 'Success', 'messageId' => 'x']])),
            ['africasTalkingUsername' => 'kommandhub', 'africasTalkingApiKey' => 'atsk-1'],
        );

        $provider->send(new MessageRequest('254700000000', 'body'));

        $this->assertArrayNotHasKey('from', $this->sentBody());
    }

    /**
     * Africa's Talking reports a per-recipient refusal inside an otherwise
     * successful envelope, so the HTTP status alone is not the answer.
     */
    public function testARefusedRecipientInsideA2xxIsPermanent(): void
    {
        $provider = $this->provider($this->client(self::envelope([
            ['statusCode' => 403, 'status' => 'UserInBlacklist', 'messageId' => 'None'],
        ])));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('UserInBlacklist');

        $provider->send(new MessageRequest('254700000000', 'body'));
    }

    public function testAnEmptyRecipientListIsPermanent(): void
    {
        $provider = $this->provider($this->client(self::envelope([], 'Invalid sender id')));

        $this->expectException(PermanentProviderException::class);
        $this->expectExceptionMessage('Invalid sender id');

        $provider->send(new MessageRequest('254700000000', 'body'));
    }

    public function testAServerErrorIsTransient(): void
    {
        $provider = $this->provider($this->client(['error' => 'down'], 502));

        $this->expectException(TransientProviderException::class);

        $provider->send(new MessageRequest('254700000000', 'body'));
    }

    /**
     * The sandbox is chosen by username, which is how the provider itself
     * models it — a second toggle could disagree with the credentials.
     */
    public function testTheSandboxUsernameSwitchesTheHost(): void
    {
        $provider = $this->provider(
            $this->client(self::envelope([['statusCode' => 101, 'status' => 'Success', 'messageId' => 'x']])),
            ['africasTalkingUsername' => 'sandbox', 'africasTalkingApiKey' => 'atsk-1'],
        );

        $provider->send(new MessageRequest('254700000000', 'body'));

        $this->assertStringStartsWith('https://api.sandbox.africastalking.com', (string)$this->capturedUrl);
    }


    public function testItClaimsEastAfricanDestinations(): void
    {
        $provider = $this->provider($this->client([]));

        $this->assertTrue($provider->supports(new MessageRequest('254700000000', 'body')));
        $this->assertFalse($provider->supports(new MessageRequest('15005550006', 'body')));
    }

    public function testVerifyCredentialsReportsTheBalance(): void
    {
        $provider = $this->provider($this->client(['UserData' => ['balance' => 'KES 1785.50']]));

        $check = $provider->verifyCredentials();

        $this->assertTrue($check->isValid());
        $this->assertStringContainsString('KES 1785.50', $check->getMessage());
    }

    public function testVerifyCredentialsNeedsBothFields(): void
    {
        $check = $this->provider($this->client([]), ['africasTalkingApiKey' => 'atsk-1'])->verifyCredentials();

        $this->assertFalse($check->isValid());
    }

    /**
     * @param array<string, string>|null $settings
     */
    private function provider(MockHttpClient $client, ?array $settings = null): AfricasTalkingProvider
    {
        return new AfricasTalkingProvider($client, $this->config($settings ?? self::SETTINGS), new NullLogger());
    }
}
