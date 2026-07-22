<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Administration\Controller;

use Kommandhub\SmsSW\Administration\Controller\TestMessageController;
use Kommandhub\SmsSW\Notification\Service\TestMessageService;
use Kommandhub\SmsSW\Notification\Struct\TestMessageResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(TestMessageController::class)]
#[UsesClass(TestMessageResult::class)]
class TestMessageControllerTest extends TestCase
{
    private TestMessageService&MockObject $testMessageService;
    private TestMessageController $controller;

    protected function setUp(): void
    {
        $this->testMessageService = $this->createMock(TestMessageService::class);
        $this->controller = new TestMessageController($this->testMessageService);
    }

    public function testSendReturnsSuccess(): void
    {
        $context = Context::createDefaultContext();
        $result = TestMessageResult::sent('msg-123', 'Hello');

        $this->testMessageService->expects($this->once())
            ->method('send')
            ->with('temp-123', '+234800', $context, 'sc-123')
            ->willReturn($result);

        $request = new Request([], ['recipient' => '+234800', 'salesChannelId' => 'sc-123']);
        $response = $this->controller->send('temp-123', $request, $context);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('msg-123', $data['messageId']);
    }

    public function testSendReturnsSuccessWithNullSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $result = TestMessageResult::sent('msg-123', 'Hello');

        $this->testMessageService->expects($this->once())
            ->method('send')
            ->with('temp-123', '+234800', $context, null)
            ->willReturn($result);

        $request = new Request([], ['recipient' => '+234800', 'salesChannelId' => '']);
        $response = $this->controller->send('temp-123', $request, $context);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSendReturnsBadRequestForMissingRecipient(): void
    {
        $request = new Request([], ['recipient' => '']);
        $response = $this->controller->send('temp-123', $request, Context::createDefaultContext());

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('missingRecipient', $data['reason']);
    }
}
