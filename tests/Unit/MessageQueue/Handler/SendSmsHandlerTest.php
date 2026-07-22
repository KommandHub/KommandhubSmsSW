<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\MessageQueue\Handler;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\MessageQueue\Handler\SendSmsHandler;
use Kommandhub\SmsSW\MessageQueue\Message\SendSmsMessage;
use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class SendSmsHandlerTest extends TestCase
{
    private NotificationGatewayInterface&MockObject $gateway;
    private SendSmsHandler $handler;

    protected function setUp(): void
    {
        $this->gateway = $this->createMock(NotificationGatewayInterface::class);

        $this->handler = new SendSmsHandler(
            $this->gateway,
            new ArrayAdapter(),
            new NullLogger(),
        );
    }

    public function testTheMessageIsPassedThroughToTheGateway(): void
    {
        $this->gateway->expects($this->once())
            ->method('send')
            ->with('2348030000000', 'body', 'sales-channel-id')
            ->willReturn('msg-1');

        ($this->handler)($this->message());
    }

    /**
     * The queue is at-least-once, and a duplicate here texts the customer twice
     * and bills the merchant twice.
     */
    public function testRedeliveryOfTheSameMessageSendsOnlyOnce(): void
    {
        $this->gateway->expects($this->once())->method('send')->willReturn('msg-1');

        $message = $this->message();

        ($this->handler)($message);
        ($this->handler)($message);
    }

    public function testDifferentDedupeKeysBothSend(): void
    {
        $this->gateway->expects($this->exactly(2))->method('send')->willReturn('msg-1');

        ($this->handler)($this->message('key-a'));
        ($this->handler)($this->message('key-b'));
    }

    /**
     * A provider rejection is permanent: swallow it so the message is not
     * retried forever into the failure transport.
     */
    public function testProviderRejectionIsNotRethrown(): void
    {
        $this->gateway->method('send')->willThrowException(new SmsException('Invalid Sender ID'));

        ($this->handler)($this->message());

        $this->expectNotToPerformAssertions();
    }

    public function testTransientFailureIsRethrownAfterReleasingKey(): void
    {
        $this->gateway->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(function () {
                static $count = 0;

                if ($count++ === 0) {
                    throw new TransientProviderException('Timeout');
                }

                return 'msg-success';
            });

        $message = $this->message('retry-key');

        try {
            ($this->handler)($message);
            $this->fail('TransientProviderException should have been rethrown');
        } catch (TransientProviderException) {
        }

        // Second attempt should work because the key was released
        ($this->handler)($message);
    }

    private function message(string $dedupeKey = 'key-a'): SendSmsMessage
    {
        return new SendSmsMessage('2348030000000', 'body', $dedupeKey, 'sales-channel-id');
    }
}
