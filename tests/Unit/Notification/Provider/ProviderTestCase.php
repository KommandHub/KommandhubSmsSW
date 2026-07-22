<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Tests\Unit\Notification\Provider;

use Kommandhub\SmsSW\Setting\Service\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Shared plumbing for the provider tests.
 *
 * Uses Symfony's MockHttpClient rather than mocking the plugin's own classes, so
 * the assertions are about the bytes each provider actually puts on the wire —
 * which is the whole of what distinguishes one provider from another.
 */
abstract class ProviderTestCase extends TestCase
{
    /**
     * Captures the request the provider made, for assertions after the call.
     */
    protected ?string $capturedUrl = null;

    protected ?string $capturedMethod = null;

    /**
     * @var array<string, mixed>
     */
    protected array $capturedOptions = [];

    /**
     * @param array<string, mixed>|string $body the response the provider will see
     * @param array<string, string> $settings keyed by full config key, e.g. "termiiApiKey"
     */
    protected function client(array|string $body, int $status = 200): MockHttpClient
    {
        $payload = \is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR);

        return new MockHttpClient(function (string $method, string $url, array $options) use ($payload, $status): MockResponse {
            $this->capturedMethod = $method;
            $this->capturedUrl = $url;
            $this->capturedOptions = $options;

            return new MockResponse($payload, [
                'http_code' => $status,
                'response_headers' => ['content-type' => 'application/json'],
            ]);
        });
    }

    /**
     * @param array<string, string> $settings
     */
    protected function config(array $settings): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('getString')->willReturnCallback(
            static fn (string $key): string => $settings[$key] ?? '',
        );

        return $config;
    }

    /**
     * The request body the provider sent, decoded.
     *
     * MockHttpClient normalises both `json` and `body` options into the raw
     * body string, so this covers JSON and form-encoded providers alike.
     *
     * @return array<string, mixed>
     */
    protected function sentBody(): array
    {
        $body = $this->capturedOptions['body'] ?? '';

        if (!\is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (\is_array($decoded)) {
            return $decoded;
        }

        parse_str($body, $parsed);

        return $parsed;
    }

    /**
     * @return array<int, string>
     */
    protected function sentHeaders(): array
    {
        $headers = $this->capturedOptions['headers'] ?? [];

        return \is_array($headers) ? array_values(array_map(strval(...), $headers)) : [];
    }
}
