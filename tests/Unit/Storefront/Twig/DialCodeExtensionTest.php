<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Storefront\Twig;

use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Storefront\DialCode\DialCodeProvider;
use Kommandhub\SmsSW\Storefront\Twig\DialCodeExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(DialCodeExtension::class)]
class DialCodeExtensionTest extends TestCase
{
    private DialCodeProvider&MockObject $dialCodeProvider;
    private Config&MockObject $config;
    private EntityRepository&MockObject $countryRepository;
    private DialCodeExtension $extension;

    protected function setUp(): void
    {
        $this->dialCodeProvider = $this->createMock(DialCodeProvider::class);
        $this->config = $this->createMock(Config::class);
        $this->countryRepository = $this->createMock(EntityRepository::class);

        $this->extension = new DialCodeExtension(
            $this->dialCodeProvider,
            $this->config,
            $this->countryRepository
        );
    }

    public function testGetFunctions(): void
    {
        $functions = $this->extension->getFunctions();
        $this->assertCount(3, $functions);
    }

    public function testGetDialCodes(): void
    {
        $this->dialCodeProvider->expects($this->once())
            ->method('all')
            ->willReturn([['iso' => 'NG', 'dialCode' => '234', 'label' => 'Nigeria (+234)']]);

        $this->assertEquals([['iso' => 'NG', 'dialCode' => '234', 'label' => 'Nigeria (+234)']], $this->extension->getDialCodes());
    }

    public function testSplitPhone(): void
    {
        $this->dialCodeProvider->expects($this->once())
            ->method('split')
            ->with('+2348000000000')
            ->willReturn(['dialCode' => '234', 'national' => '8000000000']);

        $this->assertEquals(['dialCode' => '234', 'national' => '8000000000'], $this->extension->splitPhone('+2348000000000'));
    }

    public function testGetDefaultDialCodeFromContextCountry(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);

        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getSalesChannelId')->willReturn('sc-1');
        $salesChannel->method('getCountryId')->willReturn('country-1');

        $country = new CountryEntity();
        $country->setIso('NG');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn($country);

        $this->countryRepository->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $this->dialCodeProvider->expects($this->once())
            ->method('forCountryIso')
            ->with('NG')
            ->willReturn('234');

        $this->assertEquals('234', $this->extension->getDefaultDialCode($context));
    }

    public function testGetDefaultDialCodeFromConfigFallback(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $salesChannel = $this->createMock(SalesChannelEntity::class);

        $context->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannel->method('getCountryId')->willReturn('country-1');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('first')->willReturn(null);

        $this->countryRepository->method('search')->willReturn($searchResult);

        $this->dialCodeProvider->method('forCountryIso')->willReturn(null);

        $this->config->expects($this->once())
            ->method('getString')
            ->with('defaultDialCode', $context->getSalesChannelId())
            ->willReturn('+233');

        $this->assertEquals('233', $this->extension->getDefaultDialCode($context));
    }

    public function testGetDefaultDialCodeReturnsNullWhenNothingMatches(): void
    {
        $this->countryRepository->method('search')->willReturn($this->createMock(EntitySearchResult::class));
        $this->config->method('getString')->willReturn('');

        $this->assertNull($this->extension->getDefaultDialCode(null));
    }
}
