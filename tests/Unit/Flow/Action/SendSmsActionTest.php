<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Flow\Action;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Kommandhub\SmsSW\Flow\Action\SendSmsAction;
use Kommandhub\SmsSW\MessageQueue\Message\SendSmsMessage;
use Kommandhub\SmsSW\Notification\Service\RecipientPhoneResolver;
use Kommandhub\SmsSW\Setting\Service\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(SendSmsAction::class)]
#[UsesClass(SendSmsMessage::class)]
class SendSmsActionTest extends TestCase
{
    private EntityRepository&MockObject $templateRepository;
    private RecipientPhoneResolver&MockObject $phoneResolver;
    private StringTemplateRenderer&MockObject $templateRenderer;
    private MessageBusInterface&MockObject $bus;
    private Config&MockObject $config;
    private LoggerInterface&MockObject $logger;
    private SendSmsAction $action;

    protected function setUp(): void
    {
        $this->templateRepository = $this->createMock(EntityRepository::class);
        $this->phoneResolver = $this->createMock(RecipientPhoneResolver::class);
        $this->templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new SendSmsAction(
            $this->templateRepository,
            $this->phoneResolver,
            $this->templateRenderer,
            $this->bus,
            $this->config,
            $this->logger
        );
    }

    public function testGetName(): void
    {
        $this->assertEquals('action.kommandhub.send.sms', SendSmsAction::getName());
    }

    public function testRequirements(): void
    {
        $this->assertEquals([], $this->action->requirements());
    }

    public function testHandleFlowDispatchesMessage(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getStore')->willReturnMap([
            ['salesChannelId', 'sc-123'],
            ['orderId', 'order-123'],
        ]);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $flow->method('getContext')->willReturn(Context::createDefaultContext());
        $flow->method('getName')->willReturn('order.placed');

        $order = new OrderEntity();
        $address = new OrderAddressEntity();
        $address->setPhoneNumber('+2348000000000');
        $order->setBillingAddress($address);
        $flow->method('getData')->with('order')->willReturn($order);

        $this->phoneResolver->expects($this->once())
            ->method('resolve')
            ->with('+2348000000000', $this->anything(), 'sc-123')
            ->willReturn('+2348000000000');

        $template = new SmsTemplateEntity();
        $template->setActive(true);
        $template->setContent('Hello {{ order.orderNumber }}');
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($template);
        $this->templateRepository->method('search')->willReturn($searchResult);

        $this->templateRenderer->method('render')->willReturn('Hello 1000');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendSmsMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowWithCustomerFallback(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $flow->method('getData')->willReturnMap([
            ['order', null],
            ['customer', $customer = new CustomerEntity()],
        ]);
        $address = new CustomerAddressEntity();
        $address->setPhoneNumber('+234999999');
        $customer->setDefaultBillingAddress($address);

        $this->phoneResolver->method('resolve')->willReturn('+234999999');

        $template = new SmsTemplateEntity();
        $template->setActive(true);
        $template->setContent('Hi');
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($template);
        $this->templateRepository->method('search')->willReturn($searchResult);
        $this->templateRenderer->method('render')->willReturn('Hi');

        $this->bus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowLogsWarningWhenNoTemplateConfigured(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn([]);

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('no template configured'));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowReturnsWhenNoRecipient(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $this->phoneResolver->method('resolve')->willReturn(null);

        $this->bus->expects($this->never())->method('dispatch');

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowLogsWhenTemplateNotFound(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $this->phoneResolver->method('resolve')->willReturn('+234800');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn(null);
        $this->templateRepository->method('search')->willReturn($searchResult);

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('references a template that no longer exists'));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowLogsWhenTemplateInactive(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $this->phoneResolver->method('resolve')->willReturn('+234800');

        $template = new SmsTemplateEntity();
        $template->setActive(false);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($template);
        $this->templateRepository->method('search')->willReturn($searchResult);

        $this->logger->expects($this->once())->method('info')->with($this->stringContains('template is inactive'));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowLogsWhenTemplateHasNoContent(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $this->phoneResolver->method('resolve')->willReturn('+234800');

        $template = new SmsTemplateEntity();
        $template->setActive(true);
        $template->setContent('');
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($template);
        $this->templateRepository->method('search')->willReturn($searchResult);

        $this->logger->expects($this->once())->method('warning')->with($this->stringContains('template has no content'));

        $this->action->handleFlow($flow);
    }

    public function testHandleFlowLogsErrorOnRenderFailure(): void
    {
        $flow = $this->createMock(StorableFlow::class);
        $flow->method('getConfig')->willReturn(['templateId' => 'temp-123']);
        $this->phoneResolver->method('resolve')->willReturn('+234800');

        $template = new SmsTemplateEntity();
        $template->setActive(true);
        $template->setContent('{{ invalid');
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($template);
        $this->templateRepository->method('search')->willReturn($searchResult);

        $this->templateRenderer->method('render')->willThrowException(new \Exception('Twig error'));

        $this->logger->expects($this->once())->method('error')->with($this->stringContains('template failed to render'));

        $this->action->handleFlow($flow);
    }
}
