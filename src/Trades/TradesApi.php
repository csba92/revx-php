<?php

declare(strict_types=1);

namespace Revx\Trades;

use Revx\Client;

final readonly class TradesApi
{
    public function __construct(private Client $client)
    {
    }

    /**
     * Returns public trades for one symbol or, when omitted, every available pair.
     *
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function publicHistory(?string $symbol = null, ?int $since = null, ?int $until = null, ?string $cursor = null, ?int $limit = null, ?string $region = null): array
    {
        $this->assertLimit($limit);

        return $this->client->publicGet('/api/1.0/public/trades/all', [
            'symbol' => $symbol,
            'start_date' => $since,
            'end_date' => $until,
            'cursor' => $cursor,
            'limit' => $limit,
            'region' => $region,
        ]);
    }

    /**
     * Returns trades (fills) for the authenticated account and a specific pair.
     *
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function privateHistory(string $symbol, ?int $since = null, ?int $until = null, ?string $cursor = null, ?int $limit = null): array
    {
        $this->assertLimit($limit);

        return $this->client->authenticatedGet('/api/1.0/trades/private/' . rawurlencode($symbol), [
            'start_date' => $since,
            'end_date' => $until,
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function fills(string $venueOrderId): array
    {
        $this->assertUuid($venueOrderId);

        return $this->client->authenticatedGet('/api/1.0/orders/fills/' . rawurlencode($venueOrderId));
    }

    private function assertLimit(?int $limit): void
    {
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('Trade limit must be at least one.');
        }
    }

    private function assertUuid(string $value): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new \InvalidArgumentException('Venue order ID must be an RFC 4122 UUID.');
        }
    }
}
