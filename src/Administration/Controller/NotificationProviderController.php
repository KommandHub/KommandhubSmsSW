<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Administration\Controller;

use Kommandhub\SmsSW\Exception\SmsException;
use Kommandhub\SmsSW\Notification\Provider\NotificationProviderRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration API for the provider settings screen.
 *
 * Two endpoints, both read-only: list what is available and configured, and
 * test one provider's credentials. Because both answers are derived from the
 * registry, a newly added provider appears in the administration with no change
 * to this controller.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class NotificationProviderController
{
    public function __construct(
        private readonly NotificationProviderRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Every known provider and whether this sales channel has it configured.
     */
    #[Route(
        path: '/api/_action/kommandhub-sms/provider',
        name: 'api.action.kommandhub_sms.provider.list',
        methods: ['GET'],
        defaults: ['_acl' => ['sms.manage']],
    )]
    public function list(Request $request): JsonResponse
    {
        $salesChannelId = $request->query->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;

        $providers = [];

        foreach ($this->registry->all() as $name => $provider) {
            $providers[] = [
                'name' => $name,
                'label' => $provider->getLabel(),
                'configured' => $provider->isConfigured($salesChannelId),
            ];
        }

        return new JsonResponse(['providers' => $providers]);
    }

    /**
     * Asks one provider whether its configured credentials work.
     *
     * Always answers 200 with a body: an invalid credential is an expected
     * outcome of pressing "test", not a transport error, and the administration
     * renders the reason.
     */
    #[Route(
        path: '/api/_action/kommandhub-sms/provider/{providerName}/verify',
        name: 'api.action.kommandhub_sms.provider.verify',
        methods: ['POST'],
        defaults: ['_acl' => ['sms.manage']],
    )]
    public function verify(string $providerName, Request $request): JsonResponse
    {
        $salesChannelId = $request->request->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;

        try {
            $provider = $this->registry->get($providerName);
        } catch (SmsException $exception) {
            return new JsonResponse([
                'valid' => false,
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        $check = $provider->verifyCredentials($salesChannelId);

        // The provider's own wording can name the account but never the secret;
        // it is logged for support and returned for the merchant.
        $this->logger->info('SMS provider credentials checked', [
            'provider' => $providerName,
            'valid' => $check->isValid(),
            'salesChannelId' => $salesChannelId,
        ]);

        return new JsonResponse([
            'valid' => $check->isValid(),
            'message' => $check->getMessage(),
            'detail' => $check->getDetail(),
        ]);
    }
}
