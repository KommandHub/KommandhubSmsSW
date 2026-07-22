<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Struct;

use Kommandhub\SmsSW\Notification\Struct\TestMessageResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestMessageResult::class)]
class TestMessageResultTest extends TestCase
{
    public function testSent(): void
    {
        $result = TestMessageResult::sent('msg-123', 'Hello world');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('msg-123', $result->getMessageId());
        $this->assertEquals('Hello world', $result->getRenderedBody());
        $this->assertNull($result->getReason());
        $this->assertNull($result->getDetail());
    }

    public function testFailed(): void
    {
        $result = TestMessageResult::failed('invalidRecipient', 'Provider error', 'Failed body');

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('invalidRecipient', $result->getReason());
        $this->assertEquals('Provider error', $result->getDetail());
        $this->assertEquals('Failed body', $result->getRenderedBody());
        $this->assertNull($result->getMessageId());
    }

    public function testJsonSerialize(): void
    {
        $result = TestMessageResult::sent('msg-123', 'Hello');
        $expected = [
            'success' => true,
            'reason' => null,
            'detail' => null,
            'messageId' => 'msg-123',
            'renderedBody' => 'Hello',
        ];

        $this->assertEquals($expected, $result->jsonSerialize());
    }
}
