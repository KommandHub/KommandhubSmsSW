<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Flow\Action;

use Kommandhub\SmsSW\Core\Content\SmsTemplate\SmsTemplateEntity;
use Kommandhub\SmsSW\MessageQueue\Message\SendSmsMessage;
use Kommandhub\SmsSW\Notification\Service\RecipientPhoneResolver;
use Kommandhub\SmsSW\Setting\Service\Config;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\DelayableAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Flow Builder action: send the configured SMS template to the shopper.
 *
 * Runs beside core's SendMailAction rather than instead of it: the merchant
 * adds both to the same flow, and each decides independently whether it can
 * deliver.
 *
 * **Nothing in here throws.** A flow action that throws aborts the remaining
 * sequences, which would mean a missing mobile number silently suppressing the
 * order-confirmation email. Every failure path logs and returns.
 */
class SendSmsAction extends FlowAction implements DelayableAction
{
    public function __construct(
        private readonly EntityRepository $templateRepository,
        private readonly RecipientPhoneResolver $phoneResolver,
        private readonly StringTemplateRenderer $templateRenderer,
        private readonly MessageBusInterface $bus,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return 'action.kommandhub.send.sms';
    }

    /**
     * Empty on purpose: this action serves order flows and customer flows
     * alike (order.placed, order.shipped, customer.recovery), and the recipient
     * lookup below copes with either shape.
     *
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $salesChannelId = $flow->getStore('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) ? $salesChannelId : null;

        $templateId = $flow->getConfig()['templateId'] ?? null;

        if (!\is_string($templateId) || $templateId === '') {
            $this->logger->warning('Flow action has no template configured; nothing sent');

            return;
        }

        $recipient = $this->phoneResolver->resolve(
            $this->findPhoneNumber($flow),
            $this->config->getString('defaultDialCode', $salesChannelId),
            $salesChannelId,
        );

        // Already logged by the resolver, with the reason.
        if ($recipient === null) {
            return;
        }

        $body = $this->renderTemplate($flow, $templateId, $salesChannelId);

        if ($body === null) {
            return;
        }

        $this->bus->dispatch(new SendSmsMessage(
            $recipient,
            $body,
            $this->buildDedupeKey($flow, $templateId, $recipient),
            $salesChannelId,
        ));
    }

    /**
     * Prefers the number on the order — that is what the shopper entered for
     * this delivery — and falls back to the customer account.
     */
    private function findPhoneNumber(StorableFlow $flow): ?string
    {
        $order = $flow->getData('order');

        if ($order instanceof OrderEntity) {
            $phone = $order->getBillingAddress()?->getPhoneNumber();

            if ($phone !== null && trim($phone) !== '') {
                return $phone;
            }
        }

        $customer = $flow->getData('customer');

        if ($customer instanceof CustomerEntity) {
            return $customer->getDefaultBillingAddress()?->getPhoneNumber();
        }

        return null;
    }

    private function renderTemplate(StorableFlow $flow, string $templateId, ?string $salesChannelId): ?string
    {
        $criteria = new Criteria([$templateId]);
        $template = $this->templateRepository->search($criteria, $flow->getContext())->first();

        if (!$template instanceof SmsTemplateEntity) {
            $this->logger->warning('Flow action references a template that no longer exists', [
                'templateId' => $templateId,
            ]);

            return null;
        }

        if (!$template->isActive()) {
            $this->logger->info('Skipping notification: template is inactive', [
                'templateId' => $templateId,
            ]);

            return null;
        }

        $content = $template->getContent();

        if ($content === null || $content === '') {
            $this->logger->warning('Skipping notification: template has no content for this language', [
                'templateId' => $templateId,
                'salesChannelId' => $salesChannelId,
            ]);

            return null;
        }

        try {
            // htmlEscape must be false: these are plain-text channels, and the
            // default would send "Ben &amp; Co" to a handset.
            return $this->templateRenderer->render($content, $flow->data(), $flow->getContext(), false);
        } catch (\Throwable $exception) {
            // A merchant typo in the Twig body must not abort the flow.
            $this->logger->error('Skipping notification: template failed to render', [
                'templateId' => $templateId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Identifies one logical send, so a redelivered queue message is recognised
     * as the same text rather than sent again.
     */
    private function buildDedupeKey(StorableFlow $flow, string $templateId, string $recipient): string
    {
        $subject = $flow->getStore('orderId') ?? $flow->getStore('customerId') ?? $flow->getName();

        return implode(':', ['sms', $templateId, $recipient, \is_scalar($subject) ? (string)$subject : 'none']);
    }
}
