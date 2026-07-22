<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Webhook\Controller;

use Kommandhub\SmsSW\Webhook\Controller\WebhookController;
use Kommandhub\SmsSW\Webhook\Event\WebhookEvent;
use Kommandhub\SmsSW\Webhook\Service\WebhookEventFactory;
use Kommandhub\SmsSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(WebhookController::class)]
class WebhookControllerTest extends TestCase
{
    private WebhookSignatureValidator&MockObject $signatureValidator;
    private WebhookEventFactory&MockObject $eventFactory;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoggerInterface&MockObject $logger;
    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->signatureValidator = $this->createMock(WebhookSignatureValidator::class);
        $this->eventFactory = $this->createMock(WebhookEventFactory::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new WebhookController(
            $this->signatureValidator,
            $this->eventFactory,
            $this->eventDispatcher,
            $this->logger
        );
    }

    public function testHandleDispatchesEventAndReturnsOk(): void
    {
        $payload = [
            'type' => 'outbound',
            'message_id' => 'msg_123',
            'status' => 'delivered',
        ];

        $request = new Request([], [], ['sw-sales-channel-id' => 'sc-123'], [], [], [], json_encode($payload));

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with($request, 'sc-123');

        $event = $this->createMock(WebhookEvent::class);

        $this->eventFactory->expects($this->once())
            ->method('create')
            ->with('outbound', $payload, 'sc-123')
            ->willReturn($event);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $response = $this->controller->handle($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('ok', $response->getContent());
    }

    public function testHandleReturnsIgnoredForUnknownEvent(): void
    {
        $payload = ['type' => 'unknown'];
        $request = new Request([], [], [], [], [], [], json_encode($payload));

        $this->eventFactory->expects($this->once())
            ->method('create')
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info');

        $response = $this->controller->handle($request);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString('ignored', $response->getContent());
    }

    public function testHandleThrowsOnInvalidPayload(): void
    {
        $request = new Request([], [], [], [], [], [], 'not-json');

        $this->expectException(RoutingException::class);
        $this->controller->handle($request);
    }
}
