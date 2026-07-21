<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Exception;

/**
 * The provider understood the request and refused it: bad credentials, an
 * unapproved sender ID, a malformed recipient, no credit.
 *
 * Retrying produces the identical refusal, so the queue handler logs and stops.
 * Failing over to another provider is pointless for a bad recipient but useful
 * for an unfunded account — the selector treats it as fatal for the current
 * send rather than guessing.
 */
class PermanentProviderException extends SmsException
{
}
