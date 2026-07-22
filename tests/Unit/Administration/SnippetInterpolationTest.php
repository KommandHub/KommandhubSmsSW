<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Administration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards against a failure mode that is invisible until runtime.
 *
 * vue-i18n parses `{...}` in a snippet as its own interpolation syntax, so a
 * literal `{{ order.orderNumber }}` — the obvious way to document a Twig
 * placeholder to a merchant — makes the message compiler throw
 * `SyntaxError: 9`. The throw happens while rendering the element that uses the
 * snippet, which silently removes that element *and its whole surrounding card*
 * from the page. A required field can disappear from the administration while
 * every build, lint and PHP test still passes.
 *
 * Literal braces must therefore be written with vue-i18n's literal syntax:
 * `{'{{'} order.orderNumber {'}}'}` renders as `{{ order.orderNumber }}`.
 */
class SnippetInterpolationTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function snippetFiles(): array
    {
        $root = \dirname(__DIR__, 3) . '/src/Resources/app/administration/src';
        $files = glob($root . '/module/*/snippet/*.json') ?: [];

        $cases = [];

        foreach ($files as $file) {
            $cases[basename(\dirname($file, 2)) . '/' . basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('snippetFiles')]
    public function testNoUnescapedBraces(string $file): void
    {
        $decoded = json_decode((string)file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($decoded);

        $offenders = [];
        $this->collectOffenders($decoded, '', $offenders);

        static::assertSame(
            [],
            $offenders,
            sprintf(
                "%s contains braces vue-i18n will fail to compile.\n"
                . "Write literal braces as {'{{'} … {'}}'}.\nOffending keys:\n  - %s",
                basename($file),
                implode("\n  - ", $offenders),
            ),
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $offenders
     */
    private function collectOffenders(array $node, string $prefix, array &$offenders): void
    {
        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string)$key : $prefix . '.' . $key;

            if (\is_array($value)) {
                $this->collectOffenders($value, $path, $offenders);

                continue;
            }

            if (!\is_string($value)) {
                continue;
            }

            // Strip the legitimate literal form first, then anything left over
            // is a brace vue-i18n will try to parse as an interpolation.
            $withoutLiterals = preg_replace("/\{'[^']*'\}/", '', $value) ?? $value;

            if (str_contains($withoutLiterals, '{{') || str_contains($withoutLiterals, '}}')) {
                $offenders[] = $path;
            }
        }
    }
}
