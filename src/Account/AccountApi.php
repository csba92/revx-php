<?php

declare(strict_types=1);

namespace Revx\Account;

use Revx\Client;

final readonly class AccountApi
{
    public function __construct(private Client $client)
    {
    }

    /** @return list<array{currency: string, available: string, reserved: string, total: string, staked?: string}> */
    public function balances(): array
    {
        return $this->client->authenticatedGet('/api/1.0/balances');
    }

    /**
     * @param list<string> $symbols
     * @param list<string> $states
     * @param list<string> $types
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function activeOrders(array $symbols = [], array $states = [], array $types = [], ?string $side = null, ?string $cursor = null, ?int $limit = null): array
    {
        return $this->orders('/api/1.0/orders/active', $symbols, $states, $types, $side, $cursor, $limit);
    }

    /**
     * @param list<string> $symbols
     * @param list<string> $states
     * @param list<string> $types
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function historicalOrders(array $symbols = [], array $states = [], array $types = [], ?int $since = null, ?int $until = null, ?string $cursor = null, ?int $limit = null): array
    {
        $this->assertLimit($limit);

        return $this->client->authenticatedGet('/api/1.0/orders/historical', [
            'symbols' => $symbols === [] ? null : implode(',', $symbols),
            'order_states' => $states === [] ? null : implode(',', $states),
            'order_types' => $types === [] ? null : implode(',', $types),
            'start_date' => $since,
            'end_date' => $until,
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
    }

    /** @return array{data: array<string, mixed>} */
    public function order(string $venueOrderId): array
    {
        return $this->client->authenticatedGet('/api/1.0/orders/' . rawurlencode($venueOrderId));
    }

    /**
     * @param list<string> $symbols
     * @param list<string> $states
     * @param list<string> $types
     * @return array{data: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    private function orders(string $path, array $symbols, array $states, array $types, ?string $side, ?string $cursor, ?int $limit): array
    {
        $this->assertLimit($limit);

        return $this->client->authenticatedGet($path, [
            'symbols' => $symbols === [] ? null : implode(',', $symbols),
            'order_states' => $states === [] ? null : implode(',', $states),
            'order_types' => $types === [] ? null : implode(',', $types),
            'side' => $side,
            'cursor' => $cursor,
            'limit' => $limit,
        ]);
    }

    private function assertLimit(?int $limit): void
    {
        if ($limit !== null && ($limit < 1 || $limit > 1000)) {
            throw new \InvalidArgumentException('Order limit must be between 1 and 1000.');
        }
    }
}
