<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Administration\Controller;

use Kommandhub\SmsSW\Administration\Controller\NotificationProviderController;
use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderInterface;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Kommandhub\SmsSW\Notification\Provider\Struct\CredentialCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(NotificationProviderController::class)]
#[UsesClass(CredentialCheck::class)]
class NotificationProviderControllerTest extends TestCase
{
    private NotificationProviderRegistry&MockObject $registry;
    private LoggerInterface&MockObject $logger;
    private NotificationProviderController $controller;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(NotificationProviderRegistry::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->controller = new NotificationProviderController($this->registry, $this->logger);
    }

    public function testListReturnsProviders(): void
    {
        $provider = $this->createMock(NotificationProviderInterface::class);
        $provider->method('getLabel')->willReturn('Termii');
        $provider->method('isConfigured')->with('sc-123')->willReturn(true);

        $this->registry->method('all')->willReturn(['termii' => $provider]);

        $request = new Request(['salesChannelId' => 'sc-123']);
        $response = $this->controller->list($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals([
            ['name' => 'termii', 'label' => 'Termii', 'configured' => true],
        ], $data['providers']);
    }

    public function testVerifyReturnsSuccess(): void
    {
        $provider = $this->createMock(NotificationProviderInterface::class);
        $this->registry->method('get')->with('termii')->willReturn($provider);

        $check = CredentialCheck::valid('Valid');
        $provider->method('verifyCredentials')->with('sc-123')->willReturn($check);

        $request = new Request([], ['salesChannelId' => 'sc-123']);
        $response = $this->controller->verify('termii', $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['valid']);
        $this->assertEquals('Valid', $data['message']);
    }

    public function testListReturnsProvidersWithNullSalesChannel(): void
    {
        $provider = $this->createMock(NotificationProviderInterface::class);
        $provider->method('getLabel')->willReturn('Termii');
        $provider->method('isConfigured')->with(null)->willReturn(false);

        $this->registry->method('all')->willReturn(['termii' => $provider]);

        $request = new Request(['salesChannelId' => '']);
        $response = $this->controller->list($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['providers'][0]['configured']);
    }

    public function testVerifyReturnsSuccessWithNullSalesChannel(): void
    {
        $provider = $this->createMock(NotificationProviderInterface::class);
        $this->registry->method('get')->with('termii')->willReturn($provider);

        $check = CredentialCheck::valid('Valid');
        $provider->method('verifyCredentials')->with(null)->willReturn($check);

        $request = new Request([], ['salesChannelId' => null]);
        $response = $this->controller->verify('termii', $request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testVerifyReturns404ForUnknownProvider(): void
    {
        $this->registry->method('get')->willThrowException(new SmsException('Unknown'));

        $request = new Request();
        $response = $this->controller->verify('unknown', $request);

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
