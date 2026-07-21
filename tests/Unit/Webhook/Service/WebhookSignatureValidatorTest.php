<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Service;

use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(WebhookSignatureValidator::class)]
class WebhookSignatureValidatorTest extends TestCase
{
    private const SECRET = 'test-secret';

    private const BODY = '{"event":"example.completed"}';

    private function validator(string $secret = self::SECRET): WebhookSignatureValidator
    {
        $config = $this->createMock(Config::class);
        $config->method('getBool')->willReturn(true);
        $config->method('getString')->willReturn($secret);

        return new WebhookSignatureValidator($config);
    }

    private function request(?string $signature): Request
    {
        $request = new Request([], [], [], [], [], [], self::BODY);

        if ($signature !== null) {
            $request->headers->set('x-termii-signature', $signature);
        }

        return $request;
    }

    public function testValidSignaturePasses(): void
    {
        $signature = hash_hmac('sha512', self::BODY, self::SECRET);

        $this->validator()->validate($this->request($signature));

        $this->expectNotToPerformAssertions();
    }

    public function testMissingHeaderIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->validator()->validate($this->request(null));
    }

    public function testTamperedBodyIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->validator()->validate($this->request(hash_hmac('sha512', 'other body', self::SECRET)));
    }

    /**
     * Fail closed: an unconfigured secret must never accept a request.
     */
    public function testUnconfiguredSecretIsRejected(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->validator('')->validate($this->request('anything'));
    }
}
