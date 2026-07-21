<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Webhook\Event;

/**
 * The recipient's number is on Nigeria's do-not-disturb list, so the standard
 * route cannot reach it. Reaching them needs the 'dnd' channel — see the
 * termiiChannel setting.
 */
class DndReportEvent extends WebhookEvent
{
    public static function getEventName(): string
    {
        return 'dnd';
    }
}
