<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Administration\Controller;

use Kommandhub\SmsSW\Notification\Service\TestMessageService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration API behind the "Send test message" action.
 * Answers 200 for anything the administrator can act on — an unapproved sender
 * or a malformed number is information, not a server fault. Only a genuinely
 * malformed request (a missing recipient) is a 4xx.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class TestMessageController
{
    public function __construct(private readonly TestMessageService $testMessageService)
    {
    }

    #[Route(
        path: '/api/_action/kommandhub-sms/sms-template/{templateId}/test-message',
        name: 'api.action.kommandhub_sms.test_message',
        methods: ['POST'],
        defaults: ['_acl' => ['sms.manage']],
    )]
    public function send(string $templateId, Request $request, Context $context): JsonResponse
    {
        $recipient = $request->request->get('recipient');

        if (!\is_string($recipient) || trim($recipient) === '') {
            return new JsonResponse([
                'success' => false,
                'reason' => 'missingRecipient',
            ], Response::HTTP_BAD_REQUEST);
        }

        $salesChannelId = $request->request->get('salesChannelId');
        $salesChannelId = \is_string($salesChannelId) && $salesChannelId !== '' ? $salesChannelId : null;

        $result = $this->testMessageService->send(
            $templateId,
            $recipient,
            $context,
            $salesChannelId,
        );

        return new JsonResponse($result->jsonSerialize());
    }
}
