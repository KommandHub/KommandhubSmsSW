<?php

declare(strict_types=1);

namespace Kommandhub\SmsSW\Notification\Provider;

use Kommandhub\SmsSW\Exception\PermanentProviderException;
use Kommandhub\SmsSW\Exception\TransientProviderException;
use Kommandhub\SmsSW\Notification\Provider\Struct\MessageRequest;
use Kommandhub\SmsSW\Setting\Service\Config;
use Kommandhub\SmsSW\Setting\Service\ProviderSettings;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What every HTTP-based provider needs and none of them should rewrite.
 *
 * Carries three things: the transport call, the transient-vs-permanent
 * classification of a response, and the logging shape. Subclasses supply only
 * what genuinely differs — the URL, the credential handling, the payload, and
 * how to read a message id out of the answer.
 *
 * Deliberately *not* a shared "send" implementation: a lowest-common-denominator
 * send() would force provider quirks back into shared code the moment one of
 * them needed a header the others do not have.
 */
abstract class AbstractHttpNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly Config $config,
        protected readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Country calling codes this provider claims. Empty means "anything",
     * which is what a global fallback wants.
     *
     * @return array<int, string>
     */
    protected function getCountryCodes(): array
    {
        return [];
    }

    public function supports(MessageRequest $request): bool
    {
        $codes = $this->getCountryCodes();

        if ($codes === []) {
            return true;
        }

        foreach ($codes as $code) {
            if ($request->matchesCountryCode($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads one of this provider's own settings.
     *
     * Keys are namespaced by provider name — `apiKey` on the Termii provider
     * resolves to `termiiApiKey` — which is what lets several providers hold
     * credentials at once. See ProviderSettings for the naming rule.
     */
    protected function setting(string $key, ?string $salesChannelId = null): string
    {
        return trim($this->config->getString(ProviderSettings::key($this->getName(), $key), $salesChannelId));
    }

    /**
     * Performs the call and decodes the body.
     *
     * @param array<string, mixed> $options Symfony HttpClient options
     *
     * @return array<string, mixed>
     *
     * @throws TransientProviderException
     * @throws PermanentProviderException
     */
    protected function requestJson(string $method, string $url, array $options, ?string $salesChannelId = null): array
    {
        try {
            $response = $this->httpClient->request($method, $url, $options);
            $status = $response->getStatusCode();
            // false keeps the error body: providers put the actionable reason in
            // it, and toArray()'s default would throw that away.
            $decoded = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            // DNS, TLS, timeout — the provider never saw the request.
            throw new TransientProviderException(
                sprintf('%s transport failure: %s', $this->getName(), $exception->getMessage()),
                0,
                $exception,
            );
        } catch (\Throwable $exception) {
            // Reached the provider but the body was not JSON. Usually an
            // upstream proxy or a maintenance page, so worth another attempt.
            throw new TransientProviderException(
                sprintf('%s returned an unreadable response: %s', $this->getName(), $exception->getMessage()),
                0,
                $exception,
            );
        }

        $this->logger->debug('Provider responded', [
            'provider' => $this->getName(),
            'status' => $status,
            'salesChannelId' => $salesChannelId,
        ]);

        // 429 and 5xx are the provider's problem and typically pass.
        if ($status === 429 || $status >= 500) {
            throw new TransientProviderException(sprintf(
                '%s is temporarily unavailable (HTTP %d): %s',
                $this->getName(),
                $status,
                $this->describe($decoded),
            ));
        }

        // 4xx is our problem: bad credentials, bad sender, bad number.
        if ($status >= 400) {
            throw new PermanentProviderException(sprintf(
                '%s rejected the request (HTTP %d): %s',
                $this->getName(),
                $status,
                $this->describe($decoded),
            ));
        }

        return $decoded;
    }

    /**
     * Best-effort human reason out of a provider error body.
     *
     * @param array<string, mixed> $decoded
     */
    protected function describe(array $decoded): string
    {
        foreach (['message', 'error', 'detail', 'SMSMessageData'] as $key) {
            $value = $decoded[$key] ?? null;

            if (\is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'no reason given';
    }
}
