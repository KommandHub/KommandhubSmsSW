<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Setting\Service;

/**
 * The one place that knows how a provider setting is named.
 *
 * Provider name + setting key, camel-cased: the Termii provider's `apiKey`
 * is stored as `termiiApiKey`, Twilio's `authToken` as `twilioAuthToken`. That
 * namespacing is what allows several providers to hold credentials
 * simultaneously, and it means a provider's config.xml card can be read off its
 * implementation without a lookup table.
 *
 * Kept out of the providers themselves so config.xml, the administration
 * controller and the migration all derive keys the same way.
 */
final class ProviderSettings
{
    public static function key(string $providerName, string $setting): string
    {
        return $providerName . ucfirst($setting);
    }
}
