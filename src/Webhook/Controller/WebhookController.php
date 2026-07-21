<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Controller;

use Kommandhub\SmsSW\Webhook\Service\WebhookEventFactory;
use Kommandhub\SmsSW\Webhook\Service\WebhookSignatureValidator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoint Notifications posts to.
 *
 * Deliberately thin, and deliberately generous with 200s: the signature check
 * is the security boundary, and once a request is authentic the provider must
 * be told "received" even if this plugin has nothing to do with the event.
 * Returning 4xx/5xx for an event we simply ignore makes the provider retry it
 * indefinitely.
 *
 * `csrf_protected: false` is required — the caller is a server, not a browser
 * session. `auth_required: false` likewise: authenticity comes from the HMAC.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false])]
class WebhookController extends StorefrontController
{
    public function __construct(
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly WebhookEventFactory $eventFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        path: '/notifications/webhook',
        name: 'kommandhub_sms_webhook',
        methods: ['POST'],
    )]
    public function handle(Request $request): Response
    {
        $salesChannelId = $request->attributes->getString('sw-sales-channel-id') ?: null;

        // Throws AccessDeniedHttpException (403) on anything inauthentic.
        $this->signatureValidator->validate($request, $salesChannelId);

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            throw RoutingException::invalidRequestParameter('body');
        }

        // Termii discriminates with `type` ("outbound", "inbound", "dnd"),
        // not with a dotted event name.
        $eventType = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $event = $this->eventFactory->create($eventType, $payload, $salesChannelId);

        if ($event === null) {
            $this->logger->info('Ignoring unhandled Notifications webhook', [
                'event' => $eventType,
                'salesChannelId' => $salesChannelId,
            ]);

            return new JsonResponse(['status' => 'ignored'], Response::HTTP_OK);
        }

        $this->eventDispatcher->dispatch($event);

        return new JsonResponse(['status' => 'ok'], Response::HTTP_OK);
    }
}
