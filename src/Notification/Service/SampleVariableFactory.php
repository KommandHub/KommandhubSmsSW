<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Service;

/**
 * Stand-in data for rendering a template outside a real flow.
 *
 * A test send has no order and no customer, but the template references them,
 * so Twig needs something to resolve `order.orderNumber` against. These values
 * are deliberately obvious placeholders — an administrator reading the test
 * message on their handset should never wonder whether "Jane Doe" was a real
 * shopper.
 *
 * Kept separate from the test-message service so a future preview feature can
 * render with the same sample data without going near the send path.
 */
class SampleVariableFactory
{
    /**
     * @return array<string, mixed> the Twig context for a test render
     */
    public function build(): array
    {
        return [
            'order' => [
                'orderNumber' => '10001',
                'amountTotal' => '49.99',
                'currency' => ['isoCode' => 'NGN'],
                'deliveries' => [
                    ['trackingCodes' => ['SAMPLE-TRACKING-1']],
                ],
            ],
            'customer' => [
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => 'jane.doe@example.com',
                'customerNumber' => 'SW-10000',
            ],
            'salesChannel' => [
                'name' => 'Storefront',
            ],
            // Marks the render as a test, so a template can branch on it if the
            // merchant wants an explicit "this is a test" prefix.
            'isTestMessage' => true,
        ];
    }
}
