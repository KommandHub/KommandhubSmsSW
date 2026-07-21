<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Storefront\Twig;

use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Storefront\DialCode\DialCodeProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Makes the dial-code table available to storefront templates.
 *
 * A Twig extension rather than page-data decoration: the phone field is
 * included from address forms, the checkout, the account area and CMS forms,
 * which between them are served by a dozen different page loaders. Decorating
 * every one of them to attach the same static list would be a lot of
 * subscribers for data that never varies by page.
 */
class DialCodeExtension extends AbstractExtension
{
    public function __construct(
        private readonly DialCodeProvider $dialCodeProvider,
        private readonly Config $config,
        private readonly EntityRepository $countryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('kommandhub_sms_dial_codes', $this->getDialCodes(...)),
            new TwigFunction('kommandhub_sms_split_phone', $this->splitPhone(...)),
            new TwigFunction('kommandhub_sms_default_dial_code', $this->getDefaultDialCode(...)),
        ];
    }

    /**
     * @return array<int, array{iso: string, dialCode: string, label: string}>
     */
    public function getDialCodes(): array
    {
        return $this->dialCodeProvider->all();
    }

    /**
     * @return array{dialCode: string|null, national: string}
     */
    public function splitPhone(?string $stored): array
    {
        return $this->dialCodeProvider->split($stored);
    }

    /**
     * Which dial code a fresh, empty field starts on.
     *
     * Only a starting point — the shopper can change it, which is the entire
     * reason this field exists. Resolution order:
     *
     * 1. the sales channel's own country, so a Ghanaian storefront opens on +233
     *    even though the plugin's fallback is Nigerian;
     * 2. the plugin's `defaultDialCode` setting, which predates this field and
     *    still drives server-side normalisation of legacy numbers;
     * 3. nothing, leaving the placeholder option selected so a shopper must
     *    choose rather than silently submit a wrong country.
     */
    public function getDefaultDialCode(?SalesChannelContext $context = null): ?string
    {
        $salesChannelId = $context?->getSalesChannelId();

        $iso = $this->resolveSalesChannelCountryIso($context);
        $fromCountry = $this->dialCodeProvider->forCountryIso($iso);

        if ($fromCountry !== null) {
            return $fromCountry;
        }

        $configured = trim($this->config->getString('defaultDialCode', $salesChannelId));

        return $configured !== '' ? ltrim($configured, '+') : null;
    }

    private function resolveSalesChannelCountryIso(?SalesChannelContext $context): ?string
    {
        $countryId = $context?->getSalesChannel()->getCountryId();

        if ($countryId === null) {
            return null;
        }

        $criteria = new Criteria([$countryId]);
        $criteria->addFilter(new EqualsFilter('id', $countryId));

        $country = $this->countryRepository->search($criteria, $context->getContext())->first();

        return $country instanceof CountryEntity ? $country->getIso() : null;
    }
}
