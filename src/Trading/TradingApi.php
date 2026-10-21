<?php

declare(strict_types=1);

namespace Revx\Trading;

use Revx\Client;

final readonly class TradingApi
{
    public function __construct(private Client $client)
    {
    }

    /**
     * Places a new order. The configuration matches the Revolut X API's
     * `order_configuration` object and can represent any supported order type.
     *
     * @param array<string, mixed> $configuration
     * @return array{data: list<array{venue_order_id: string, client_order_id: string, state: string}>}
     */
    public function place(string $clientOrderId, string $symbol, string $side, array $configuration): array
    {
        $this->assertUuid($clientOrderId, 'Client order ID');
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new \InvalidArgumentException('Order side must be "buy" or "sell".');
        }
        if ($configuration === []) {
            throw new \InvalidArgumentException('Order configuration must not be empty.');
        }

        return $this->client->authenticatedRequest('POST', '/api/1.0/orders', payload: [
            'client_order_id' => $clientOrderId,
            'symbol' => $symbol,
            'side' => $side,
            'order_configuration' => $configuration,
        ]);
    }

    /**
     * Atomically replaces an active order. `changes` is forwarded unchanged to
     * the API, allowing every replaceable field supported by Revolut X.
     *
     * @param array<string, mixed> $changes
     * @return array{data: list<array{venue_order_id: string, client_order_id: string, state: string}>}
     */
    public function replace(string $venueOrderId, string $clientOrderId, array $changes): array
    {
        $this->assertUuid($venueOrderId, 'Venue order ID');
        $this->assertUuid($clientOrderId, 'Client order ID');
        if ($changes === []) {
            throw new \InvalidArgumentException('At least one order change is required.');
        }
        if (array_key_exists('client_order_id', $changes)) {
            throw new \InvalidArgumentException('Pass the replacement client order ID as the second argument, not in changes.');
        }

        return $this->client->authenticatedRequest(
            'PUT',
            '/api/1.0/orders/' . rawurlencode($venueOrderId),
            payload: ['client_order_id' => $clientOrderId] + $changes,
        );
    }

    /** Cancels one active order. */
    public function cancel(string $venueOrderId): void
    {
        $this->assertUuid($venueOrderId, 'Venue order ID');
        $this->client->authenticatedRequest('DELETE', '/api/1.0/orders/' . rawurlencode($venueOrderId));
    }

    /** Cancels every active order on the authenticated account. */
    public function cancelAll(): void
    {
        $this->client->authenticatedRequest('DELETE', '/api/1.0/orders');
    }

    private function assertUuid(string $value, string $label): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new \InvalidArgumentException($label . ' must be an RFC 4122 UUID.');
        }
    }
}
