<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Storefront\DialCode;

use Kommandhub\SmsSW\Storefront\DialCode\DialCodeProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DialCodeProviderTest extends TestCase
{
    private DialCodeProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new DialCodeProvider();
    }

    public function testEveryOptionCarriesIsoDialCodeAndLabel(): void
    {
        $options = $this->provider->all();

        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $option['iso']);
            $this->assertMatchesRegularExpression('/^\d{1,4}$/', $option['dialCode']);
            $this->assertSame(sprintf('%s +%s', $option['iso'], $option['dialCode']), $option['label']);
        }
    }

    /**
     * The ISO code is the option identity precisely because dial codes repeat —
     * +1 spans the US, Canada and much of the Caribbean.
     */
    public function testIsoCodesAreUniqueEvenWhereDialCodesRepeat(): void
    {
        $isoCodes = array_column($this->provider->all(), 'iso');

        $this->assertSame($isoCodes, array_unique($isoCodes));
        $this->assertGreaterThan(1, \count(array_keys(array_column($this->provider->all(), 'dialCode'), '1')));
    }

    public function testLookupByCountryIsoIsCaseInsensitive(): void
    {
        $this->assertSame('234', $this->provider->forCountryIso('NG'));
        $this->assertSame('234', $this->provider->forCountryIso('ng'));
        $this->assertSame('233', $this->provider->forCountryIso('GH'));
        $this->assertNull($this->provider->forCountryIso('ZZ'));
        $this->assertNull($this->provider->forCountryIso(null));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string, 2: string}>
     */
    public static function storedNumbers(): array
    {
        return [
            'plus form splits' => ['+2348031234567', '234', '8031234567'],
            'double-zero form splits' => ['002348031234567', '234', '8031234567'],
            'separators are ignored' => ['+234 803 123-4567', '234', '8031234567'],

            // The reason split() matches longest-first: +234 must not be read as
            // +23 (which is nobody) leaving a mangled national part.
            'longest dial code wins' => ['+233201234567', '233', '201234567'],
            'short dial code still works' => ['+15551234567', '1', '5551234567'],

            // Without a "+" or "00" there is no dial code to find. Treating a
            // bare national number as international would turn "08031234567"
            // into +80 31234567 and text a stranger in Japan.
            'bare national number keeps its digits' => ['08031234567', null, '08031234567'],

            'empty is empty' => ['', null, ''],
            'null is empty' => [null, null, ''],
            'letters alone are empty' => ['not a phone', null, ''],

            // A legacy value nothing matches stays visible and editable rather
            // than being silently truncated.
            'unknown dial code survives whole' => ['+9995551234', null, '9995551234'],
        ];
    }

    #[DataProvider('storedNumbers')]
    public function testSplit(?string $stored, ?string $dialCode, string $national): void
    {
        $result = $this->provider->split($stored);

        $this->assertSame($dialCode, $result['dialCode']);
        $this->assertSame($national, $result['national']);
    }

    /**
     * Round-tripping is what makes a rejected form redisplay correctly: the
     * merged value the browser submitted has to come back apart into the same
     * two fields.
     */
    public function testSplitRoundTripsWhatTheStorefrontMerges(): void
    {
        $merged = '+2348031234567';
        $result = $this->provider->split($merged);

        $this->assertSame($merged, '+' . $result['dialCode'] . $result['national']);
    }
}
