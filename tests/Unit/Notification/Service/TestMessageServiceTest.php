<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Service;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use Kommandhub\SmsSW\Notification\Service\RecipientPhoneResolver;
use Kommandhub\SmsSW\Notification\Service\SampleVariableFactory;
use Kommandhub\SmsSW\Notification\Service\TestMessageService;
use Kommandhub\SmsSW\Setting\Service\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class TestMessageServiceTest extends TestCase
{
    private EntityRepository&MockObject $repository;
    private StringTemplateRenderer&MockObject $renderer;
    private NotificationGatewayInterface&MockObject $gateway;
    private Context $context;

    /** Flipped by the test that covers having no configured provider. */
    private bool $providerConfigured = true;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->renderer = $this->createMock(StringTemplateRenderer::class);
        $this->gateway = $this->createMock(NotificationGatewayInterface::class);
        $this->context = Context::createDefaultContext();

        // A callback, not willReturn: PHPUnit keeps the first matcher for a
        // method, so a per-test override would be ignored.
        $this->gateway->method('isConfigured')->willReturnCallback(fn (): bool => $this->providerConfigured);
    }

    public function testASuccessfulSendReportsTheMessageIdAndRenderedBody(): void
    {
        $this->templateExists('Hi {{ customer.firstName }}');
        $this->renderer->method('render')->willReturn('Hi Jane');
        $this->gateway->expects($this->once())
            ->method('send')
            ->with('2348030000000', 'Hi Jane', null)
            ->willReturn('msg-1');

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('msg-1', $result->getMessageId());
        $this->assertSame('Hi Jane', $result->getRenderedBody());
    }

    /**
     * Plain text on a handset — an escaped ampersand would be a visible defect.
     */
    public function testTheBodyIsRenderedWithoutHtmlEscaping(): void
    {
        $this->templateExists('Ben & Co');
        $this->renderer->expects($this->once())
            ->method('render')
            ->with('Ben & Co', $this->isType('array'), $this->context, false)
            ->willReturn('Ben & Co');
        $this->gateway->method('send')->willReturn('msg-1');

        $this->service()->send('tpl-1', '08030000000', $this->context);
    }

    public function testAMissingTemplateIsReportedAndNothingIsSent(): void
    {
        $this->templateMissing();
        $this->gateway->expects($this->never())->method('send');

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('templateNotFound', $result->getReason());
    }

    public function testAnEmptyTemplateIsReportedAndNothingIsSent(): void
    {
        $this->templateExists('   ');
        $this->gateway->expects($this->never())->method('send');

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('templateEmpty', $result->getReason());
    }

    /**
     * A merchant typo in the Twig body must cost nothing.
     */
    public function testARenderFailureIsReportedBeforeAnythingIsDialled(): void
    {
        $this->templateExists('{{ broken');
        $this->renderer->method('render')->willThrowException(new \RuntimeException('unexpected token'));
        $this->gateway->expects($this->never())->method('send');

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('renderFailed', $result->getReason());
        $this->assertStringContainsString('unexpected token', (string)$result->getDetail());
    }

    public function testAnUnusableNumberIsReportedAndNothingIsSent(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');
        $this->gateway->expects($this->never())->method('send');

        $result = $this->service()->send('tpl-1', 'not a phone', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('invalidRecipient', $result->getReason());
        // The rendered body still comes back, so the administrator can see the
        // template was fine and only the number was wrong.
        $this->assertSame('Hi', $result->getRenderedBody());
    }

    /**
     * Reporting a send as successful when no provider is configured would tell
     * an administrator their setup works while nothing left the building.
     */
    public function testNoConfiguredProviderIsReportedInsteadOfFakingSuccess(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');
        $this->providerConfigured = false;
        $this->gateway->expects($this->never())->method('send');

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('noProviderConfigured', $result->getReason());
    }

    public function testAProviderRefusalIsReportedWithItsOwnWording(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');
        $this->gateway->method('send')
            ->willThrowException(new PermanentProviderException('Invalid Sender ID'));

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('providerRejected', $result->getReason());
        $this->assertStringContainsString('Invalid Sender ID', (string)$result->getDetail());
    }

    /**
     * Distinguished from a refusal so the administrator is told to retry rather
     * than sent hunting through their credentials.
     */
    public function testATransientFailureIsReportedSeparately(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');
        $this->gateway->method('send')
            ->willThrowException(new TransientProviderException('timeout'));

        $result = $this->service()->send('tpl-1', '08030000000', $this->context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('providerUnavailable', $result->getReason());
    }

    /**
     * ConfigurableLogger writes errors regardless of the debug setting, so a
     * provider failure must be an error or a merchant reporting "the test
     * button doesn't work" leaves no trace at all.
     */
    public function testAProviderFailureIsLoggedAtErrorLevel(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');
        $this->gateway->method('send')
            ->willThrowException(new PermanentProviderException('Invalid Sender ID'));

        $logger = new CollectingLogger();

        $this->service($logger)->send('tpl-1', '08030000000', $this->context);

        $this->assertContains('error', $logger->levels);
    }

    /**
     * The administrator's own typo is visible on screen; logging it at error
     * level would fill the log with noise the merchant cannot act on.
     */
    public function testAnInvalidRecipientIsNotLoggedAtErrorLevel(): void
    {
        $this->templateExists('Hi');
        $this->renderer->method('render')->willReturn('Hi');

        $logger = new CollectingLogger();

        $this->service($logger)->send('tpl-1', 'not a phone', $this->context);

        $this->assertNotContains('error', $logger->levels);
    }


    private function templateExists(string $content): void
    {
        $entity = new SmsTemplateEntity();
        $entity->setUniqueIdentifier('tpl-1');
        $entity->setContent($content);

        $this->searchReturns($entity);
    }

    private function templateMissing(): void
    {
        $this->searchReturns(null);
    }

    private function searchReturns(?SmsTemplateEntity $entity): void
    {
        $collection = new EntityCollection($entity === null ? [] : [$entity]);

        $this->repository->method('search')->willReturn(
            new EntitySearchResult(
                'kommandhub_sms_template',
                $collection->count(),
                $collection,
                null,
                new \Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria(),
                Context::createDefaultContext(),
            ),
        );
    }

    private function service(?LoggerInterface $logger = null): TestMessageService
    {
        $config = $this->createMock(Config::class);
        $config->method('getString')->willReturn('234');

        return new TestMessageService(
            $this->repository,
            new RecipientPhoneResolver(new NullLogger()),
            $this->renderer,
            new SampleVariableFactory(),
            $this->gateway,
            $config,
            $logger ?? new NullLogger(),
        );
    }
}

/**
 * Records the levels it was called with, so a test can assert on severity
 * without reaching for a mock of every PSR-3 method.
 */
class CollectingLogger extends AbstractLogger
{
    /** @var array<int, string> */
    public array $levels = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->levels[] = (string) $level;
    }
}
