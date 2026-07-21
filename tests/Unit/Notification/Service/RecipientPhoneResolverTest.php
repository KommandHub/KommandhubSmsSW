<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Service;

use Kommandhub\SmsSW\Notification\Service\RecipientPhoneResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RecipientPhoneResolverTest extends TestCase
{
    private RecipientPhoneResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RecipientPhoneResolver(new NullLogger());
    }

    /**
     * @return array<string, array{0: ?string, 1: string, 2: ?string}>
     */
    public static function numbers(): array
    {
        return [
            'national number gains the dial code' => ['08030000000', '234', '2348030000000'],
            'plus form is kept' => ['+2348030000000', '234', '2348030000000'],
            'double-zero form is kept' => ['002348030000000', '234', '2348030000000'],
            'separators are stripped' => ['+234 803 000-0000', '234', '2348030000000'],
            'already international without prefix' => ['2348030000000', '234', '2348030000000'],
            'dial code may itself be entered with a plus' => ['08030000000', '+234', '2348030000000'],

            'null is not usable' => [null, '234', null],
            'empty is not usable' => ['', '234', null],
            'whitespace is not usable' => ['   ', '234', null],
            'letters alone are not usable' => ['not a phone', '234', null],
            'too short is not usable' => ['0803', '234', null],
            'too long is not usable' => ['+2348030000000000000', '234', null],
            'national number without a dial code is skipped, not guessed' => ['08030000000', '', null],
        ];
    }

    #[DataProvider('numbers')]
    public function testResolve(?string $raw, string $dialCode, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver->resolve($raw, $dialCode));
    }

    public function testNullCustomerIsSkippedRatherThanFatal(): void
    {
        $this->assertNull($this->resolver->resolveFromCustomer(null, '234'));
    }

    public function testNullOrderIsSkippedRatherThanFatal(): void
    {
        $this->assertNull($this->resolver->resolveFromOrder(null, '234'));
    }
}
