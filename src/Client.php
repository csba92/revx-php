<?php

declare(strict_types=1);

namespace Revx;

use Revx\Account\AccountApi;
use Revx\Auth\RequestSigner;
use Revx\Exception\ApiException;
use Revx\Exception\AuthenticationException;
use Revx\Http\CurlHttpClient;
use Revx\Http\HttpClientInterface;
use Revx\Market\MarketApi;
use Revx\Trading\TradingApi;

final class Client
{
    public const BASE_URL = 'https://revx.revolut.com';

    private readonly HttpClientInterface $http;
    private readonly ?RequestSigner $signer;
    private readonly string $baseUrl;
    private readonly MarketApi $market;
    private readonly AccountApi $account;
    private readonly TradingApi $trading;

    public function __construct(
        private readonly ?string $apiKey = null,
        ?string $privateKeyPem = null,
        ?HttpClientInterface $http = null,
        string $baseUrl = self::BASE_URL,
        private readonly int $timeout = 30,
    ) {
        if ($timeout < 1) {
            throw new \InvalidArgumentException('Timeout must be at least one second.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->assertBaseUrl();
        $this->http = $http ?? new CurlHttpClient();
        $this->signer = $privateKeyPem === null ? null : new RequestSigner($privateKeyPem);
        $this->market = new MarketApi($this);
        $this->account = new AccountApi($this);
        $this->trading = new TradingApi($this);
    }

    public static function fromEnvironment(?HttpClientInterface $http = null): self
    {
        $key = self::environment('REVX_API_KEY');
        $privateKey = self::environment('REVX_PRIVATE_KEY');
        $privateKeyPath = self::environment('REVX_PRIVATE_KEY_PATH');

        if ($privateKey === null && $privateKeyPath !== null) {
            $privateKey = @file_get_contents($privateKeyPath) ?: null;
            if ($privateKey === null) {
                throw new AuthenticationException('Unable to read REVX_PRIVATE_KEY_PATH.');
            }
        }

        return new self(
            apiKey: $key,
            privateKeyPem: $privateKey,
            http: $http,
            baseUrl: self::environment('REVX_BASE_URL') ?? self::BASE_URL,
            timeout: (int) (self::environment('REVX_TIMEOUT') ?? 30),
        );
    }

    public function market(): MarketApi
    {
        return $this->market;
    }

    public function account(): AccountApi
    {
        return $this->account;
    }

    public function trading(): TradingApi
    {
        return $this->trading;
    }

    public function isAuthenticated(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '' && $this->signer !== null;
    }

    /** @param array<string, scalar|null> $query */
    public function publicGet(string $path, array $query = []): array
    {
        return $this->request('GET', $path, $query, null, false);
    }

    /** @param array<string, scalar|null> $query */
    public function authenticatedGet(string $path, array $query = []): array
    {
        return $this->authenticatedRequest('GET', $path, $query);
    }

    /**
     * Sends a signed request to an authenticated Revolut X endpoint.
     *
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $payload
     */
    public function authenticatedRequest(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        if (!$this->isAuthenticated()) {
            throw new AuthenticationException('Authenticated endpoints require an API key and Ed25519 private key.');
        }

        return $this->request($method, $path, $query, $payload, true);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null $payload
     */
    private function request(string $method, string $path, array $query, ?array $payload, bool $authenticated): array
    {
        $method = strtoupper($method);
        $query = $this->buildQuery($query);
        $url = $this->baseUrl . $path . ($query === '' ? '' : '?' . $query);
        $headers = ['Accept' => 'application/json'];
        $body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        if ($authenticated) {
            $timestamp = (string) (int) floor(microtime(true) * 1000);
            $headers['X-Revx-API-Key'] = (string) $this->apiKey;
            $headers['X-Revx-Timestamp'] = $timestamp;
            $headers['X-Revx-Signature'] = $this->signer->sign($timestamp, $method, $path, $query, $body ?? '');
        }

        $response = $this->http->request($method, $url, $headers, $body, $this->timeout);
        $decoded = $response->body === '' ? [] : json_decode($response->body, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($response->status < 200 || $response->status >= 300) {
            $message = $decoded['message'] ?? $decoded['error'] ?? sprintf('Revolut X returned HTTP %d.', $response->status);
            $retryAfter = isset($response->headers['retry-after']) ? (int) $response->headers['retry-after'] : null;
            throw new ApiException((string) $message, $response->status, $decoded, $retryAfter);
        }

        return $decoded;
    }

    /** @param array<string, scalar|null> $query */
    private function buildQuery(array $query): string
    {
        $filtered = array_filter($query, static fn (mixed $value): bool => $value !== null);

        return http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }

    private function assertBaseUrl(): void
    {
        $parts = parse_url($this->baseUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Base URL must be an absolute URL.');
        }

        $local = in_array($parts['host'], ['localhost', '127.0.0.1', '::1'], true);
        if ($parts['scheme'] !== 'https' && !$local) {
            throw new \InvalidArgumentException('Base URL must use HTTPS outside localhost.');
        }
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : $value;
    }
}
