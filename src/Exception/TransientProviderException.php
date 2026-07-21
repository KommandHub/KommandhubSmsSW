<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Exception;

/**
 * The send failed for a reason that may not recur: a timeout, a DNS blip, a
 * 5xx, a rate limit.
 *
 * Retrying is worthwhile, so the queue handler rethrows this and lets Messenger
 * apply its backoff, and the selector may try the next configured provider.
 */
class TransientProviderException extends SmsException
{
}
