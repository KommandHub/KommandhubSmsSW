<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Service;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Kommandhub\SmsSW\Notification\Gateway\NotificationGatewayInterface;
use Kommandhub\SmsSW\Notification\Struct\TestMessageResult;
use Kommandhub\SmsSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Sends one template to one number, on demand, for an administrator.
 *
 * Kept separate from the flow action because the two differ only in where the
 * variables come from — a live flow versus sample data — and this one must
 * report *why* it failed rather than quietly skipping.
 *
 * Every failure returns a TestMessageResult instead of throwing. The caller is
 * a person pressing a button to find out what is wrong; "your sender ID is not
 * approved" is the answer they wanted, not an exception.
 *
 * The template is deliberately loaded and rendered *before* the recipient is
 * dialled, so an unrenderable template never costs the merchant a message.
 */
class TestMessageService
{
    public function __construct(
        private readonly EntityRepository $templateRepository,
        private readonly RecipientPhoneResolver $phoneResolver,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly SampleVariableFactory $sampleVariables,
        private readonly NotificationGatewayInterface $gateway,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function send(
        string $templateId,
        string $recipient,
        Context $context,
        ?string $salesChannelId = null,
    ): TestMessageResult {
        $this->logger->info('Test message requested', [
            'templateId' => $templateId,
            'recipient' => $this->mask($recipient),
            'salesChannelId' => $salesChannelId,
        ]);

        $template = $this->loadTemplate($templateId, $context);

        if ($template === null) {
            return $this->fail('templateNotFound');
        }

        $content = $template->getContent();

        if ($content === null || trim($content) === '') {
            return $this->fail('templateEmpty');
        }

        try {
            // htmlEscape false: SMS is plain text, and the default
            // would put "&amp;" on a handset.
            $body = $this->templateRenderer->render(
                $content,
                $this->sampleVariables->build(),
                $context,
                false,
            );
        } catch (\Throwable $exception) {
            return $this->fail('renderFailed', $exception->getMessage());
        }

        // Validated after rendering so a broken template is reported as a
        // template problem rather than blamed on the number.
        $normalised = $this->phoneResolver->resolve(
            $recipient,
            $this->config->getString('defaultDialCode', $salesChannelId),
            $salesChannelId,
        );

        if ($normalised === null) {
            return $this->fail('invalidRecipient', null, $body);
        }

        // Asked before sending so "nothing is set up" is reported as itself
        // rather than as whatever the first send attempt happens to fail with.
        if (!$this->gateway->isConfigured($salesChannelId)) {
            return $this->fail('noProviderConfigured', null, $body);
        }

        try {
            $messageId = $this->gateway->send($normalised, $body, $salesChannelId);
        } catch (TransientProviderException $exception) {
            // Worth trying again by hand in a moment — say so rather than
            // implying the configuration is wrong.
            return $this->fail('providerUnavailable', $exception->getMessage(), $body);
        } catch (PermanentProviderException $exception) {
            return $this->fail('providerRejected', $exception->getMessage(), $body);
        }

        $this->logger->info('Test message sent', [
            'templateId' => $templateId,
            'messageId' => $messageId,
            'salesChannelId' => $salesChannelId,
        ]);

        return TestMessageResult::sent($messageId, $body);
    }

    /**
     * Inactive templates are loaded on purpose: testing a template before
     * switching it on is the normal order of work.
     */
    private function loadTemplate(string $templateId, Context $context): ?SmsTemplateEntity
    {
        $criteria = new Criteria([$templateId]);
        $criteria->addAssociation('translations');

        $template = $this->templateRepository->search($criteria, $context)->first();

        return $template instanceof SmsTemplateEntity ? $template : null;
    }

    /**
     * Reasons that mean the provider itself refused or was unreachable.
     *
     * These are logged at error level so ConfigurableLogger writes them even
     * with debug logging switched off — a merchant reporting "the test button
     * doesn't work" must leave a trace someone can read. The remaining reasons
     * are the administrator's own input and stay gated behind the debug
     * setting, because they are self-evident on screen and the log line would
     * otherwise be noise.
     */
    private const PROVIDER_FAILURES = ['providerRejected', 'providerUnavailable'];

    private function fail(
        string $reason,
        ?string $detail = null,
        ?string $renderedBody = null,
    ): TestMessageResult {
        $context = [
            'reason' => $reason,
            'detail' => $detail,
        ];

        if (\in_array($reason, self::PROVIDER_FAILURES, true)) {
            $this->logger->error('Test message not sent', $context);
        } else {
            $this->logger->warning('Test message not sent', $context);
        }

        return TestMessageResult::failed($reason, $detail, $renderedBody);
    }

    /**
     * A phone number is personal data, so the log keeps only enough to match a
     * line against the number the administrator typed.
     */
    private function mask(string $recipient): string
    {
        $digits = preg_replace('/\D+/', '', $recipient) ?? '';

        return $digits === '' ? '(empty)' : '…' . substr($digits, -4);
    }
}
