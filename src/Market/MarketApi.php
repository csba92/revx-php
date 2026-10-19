<?php

declare(strict_types=1);

namespace Revx\Market;

use Revx\Client;

final readonly class MarketApi
{
    public function __construct(private Client $client)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function currencies(?string $region = null): array
    {
        return $this->client->publicGet('/api/1.0/public/configuration/currencies', ['region' => $region]);
    }

    /** @return array<string, array<string, mixed>> */
    public function pairs(?string $region = null): array
    {
        return $this->client->publicGet('/api/1.0/public/configuration/pairs', ['region' => $region]);
    }

    /** @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>} */
    public function tickers(array $symbols = [], ?string $region = null): array
    {
        return $this->client->publicGet('/api/1.0/public/tickers', [
            'symbols' => $symbols === [] ? null : implode(',', $symbols),
            'region' => $region,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function ticker(string $symbol, ?string $region = null): ?array
    {
        $response = $this->tickers([$symbol], $region);

        return $response['data'][0] ?? null;
    }

    /**
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function candles(string $symbol, int $interval, ?int $since = null, ?int $until = null, ?string $region = null): array
    {
        if ($interval < 1) {
            throw new \InvalidArgumentException('Candle interval must be expressed as positive minutes.');
        }

        return $this->client->publicGet('/api/1.0/public/candles/' . rawurlencode($symbol), [
            'interval' => $interval,
            'since' => $since,
            'until' => $until,
            'region' => $region,
        ]);
    }

    /** @return array{data: array{asks: list<array<string, mixed>>, bids: list<array<string, mixed>>}, metadata: array<string, mixed>} */
    public function orderBook(string $symbol, int $limit = 50, ?string $region = null): array
    {
        if ($limit < 1 || $limit > 192) {
            throw new \InvalidArgumentException('Order book limit must be between 1 and 192.');
        }

        return $this->client->publicGet('/api/2.0/public/order-book/' . rawurlencode($symbol), [
            'limit' => $limit,
            'region' => $region,
        ]);
    }
}
