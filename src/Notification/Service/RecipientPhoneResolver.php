<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * The single answer to "can we text this recipient?".
 *
 * Shopware's phone number is optional and unvalidated: merchants may not
 * collect it, shoppers type landlines, and formatting is whatever the keyboard
 * produced. Both channels and both flow actions resolve through here so the
 * rules never drift apart.
 *
 * **This class never throws.** An absent or unusable number returns null, the
 * caller skips its channel, and every other channel — including the core email
 * Shopware was already going to send — still fires. A notification plugin must
 * not be able to break a checkout.
 */
class RecipientPhoneResolver
{
    /**
     * Shortest plausible national number, and the E.164 ceiling.
     */
    private const MIN_DIGITS = 8;

    private const MAX_DIGITS = 15;

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return string|null E.164 digits without a leading "+", ready for the gateways
     */
    public function resolve(?string $raw, string $defaultDialCode, ?string $salesChannelId = null): ?string
    {
        if ($raw === null || trim($raw) === '') {
            $this->logger->info('Skipping mobile channel: recipient has no phone number', [
                'salesChannelId' => $salesChannelId,
            ]);

            return null;
        }

        $digits = $this->normalise($raw, $defaultDialCode);

        if ($digits === null) {
            $this->logger->info('Skipping mobile channel: recipient phone number is not usable', [
                // The raw value is deliberately not logged — it is personal data,
                // and the length is enough to debug a formatting problem.
                'length' => \strlen($raw),
                'salesChannelId' => $salesChannelId,
            ]);

            return null;
        }

        return $digits;
    }

    public function resolveFromCustomer(?CustomerEntity $customer, string $defaultDialCode, ?string $salesChannelId = null): ?string
    {
        return $this->resolve($customer?->getDefaultBillingAddress()?->getPhoneNumber(), $defaultDialCode, $salesChannelId);
    }

    /**
     * Prefers the number captured on the order itself: it is what the shopper
     * entered for *this* delivery, and it survives later edits to the account.
     */
    public function resolveFromOrder(?OrderEntity $order, string $defaultDialCode, ?string $salesChannelId = null): ?string
    {
        $phone = $order?->getBillingAddress()?->getPhoneNumber()
            ?: $order?->getOrderCustomer()?->getCustomer()?->getDefaultBillingAddress()?->getPhoneNumber();

        return $this->resolve($phone, $defaultDialCode, $salesChannelId);
    }

    /**
     * ponytail: deliberately naive normalisation — strip separators, expand a
     * national leading zero with the configured dial code, bounds-check the
     * length. It cannot tell a mobile from a landline, which is why a landline
     * costs one wasted message rather than an error. Swap in
     * giggsey/libphonenumber-for-php if merchants report real misrouting; the
     * signature above does not change.
     */
    private function normalise(string $raw, string $defaultDialCode): ?string
    {
        $trimmed = trim($raw);
        $international = str_starts_with($trimmed, '+') || str_starts_with($trimmed, '00');

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($international) {
            // "00234803…" and "+234803…" both mean the same thing.
            $digits = ltrim($digits, '0');
        } elseif (str_starts_with($digits, '0')) {
            $dialCode = preg_replace('/\D+/', '', $defaultDialCode) ?? '';

            if ($dialCode === '') {
                // A national number with nothing to expand it against is a guess,
                // and a guess here texts a stranger.
                return null;
            }

            $digits = $dialCode . ltrim($digits, '0');
        }

        $length = \strlen($digits);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            return null;
        }

        return $digits;
    }
}
