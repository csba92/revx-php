# revx-php

An unofficial, mostly vibe-coded, PHP 8.4+ client for the [Revolut X REST API](https://developer.revolut.com/docs/api/revolut-x-crypto-exchange).
It covers public market data, authenticated account data, and signed order lifecycle operations without framework dependencies.

## Requirements

- PHP 8.4 or later
- `curl`, `openssl`, `sodium`, and `json` PHP extensions
- An Ed25519 private key and Revolut X API key for account endpoints

## Installation

Install from Packagist once the repository has been published:

```bash
composer require revx-php/revx-php
```

Until then, Composer can install a tagged GitHub release directly. Replace the repository URL with the account that owns `revx-php`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/your-account/revx-php" }
  ]
}
```

```bash
composer require revx-php/revx-php:^1.0
```

For development from a clone:

```bash
composer install
```

## Quick start

Public market data needs no credentials:

```php
use Revx\Client;

$client = new Client();

$ticker = $client->market()->ticker('BTC-USD');
$book = $client->market()->orderBook('BTC-USD');
```

For account data, pass credentials from your application's configuration:

```php
use Revx\Client;

$client = new Client(
    apiKey: $config['revx_api_key'],
    privateKeyPem: $config['revx_private_key'],
);

$balances = $client->account()->balances();
$orders = $client->account()->activeOrders(symbols: ['BTC-USD']);
```

All monetary values are returned by the API as strings. Keep them as strings or use a decimal library; avoid converting them to floats for financial calculations.

## Environment configuration

`Client::fromEnvironment()` reads the following optional variables:

| Variable | Purpose |
| --- | --- |
| `REVX_API_KEY` | Revolut X API key |
| `REVX_PRIVATE_KEY` | PEM-encoded Ed25519 private key |
| `REVX_PRIVATE_KEY_PATH` | Path to a PEM key; used when `REVX_PRIVATE_KEY` is absent |
| `REVX_BASE_URL` | API host override, useful for controlled test environments |
| `REVX_TIMEOUT` | Request timeout in seconds; defaults to `30` |

Use either `REVX_PRIVATE_KEY` or `REVX_PRIVATE_KEY_PATH`. The private-key value takes precedence.

```php
$client = \Revx\Client::fromEnvironment();
```

Do not commit a `.env` file or a PEM file. The supplied `.gitignore` excludes both.

## API

### Market data

```php
$market = $client->market();

$currencies = $market->currencies(region: 'EEA');
$pairs = $market->pairs(region: 'EEA');

$ticker = $market->ticker('BTC-USD');
$tickers = $market->tickers(['BTC-USD', 'ETH-USD']);

// Interval is expressed in minutes.
$candles = $market->candles('BTC-USD', interval: 60, since: 1735689600000);

// Public order book, with 1–192 levels per side.
$orderBook = $market->orderBook('BTC-USD', limit: 50, region: 'EEA');
```

`ticker()` returns the first ticker object or `null` when the requested symbol is not returned. The collection methods return the API response unchanged.

### Account data

Account endpoints require both an API key and an Ed25519 private key.

```php
$account = $client->account();

$balances = $account->balances();

$active = $account->activeOrders(
    symbols: ['BTC-USD'],
    states: ['new', 'partially_filled'],
    types: ['limit'],
    side: 'buy',
    limit: 100,
);

$history = $account->historicalOrders(
    symbols: ['BTC-USD'],
    states: ['filled', 'cancelled'],
    since: 1735689600000,
    until: 1735776000000,
);

$order = $account->order('venue-order-id');
```

List endpoints preserve the exchange's `{ data, metadata }` response shape, including any pagination cursor in `metadata.next_cursor`.

### Trades and fills

```php
$trades = $client->trades();

// Public trade history can cover one pair or every pair when symbol is omitted.
$public = $trades->publicHistory(
    symbol: 'BTC-USD',
    since: 1735689600000,
    until: 1735776000000,
    limit: 100,
    region: 'EEA',
);

// Account trade history (your fills) is scoped to a single pair.
$private = $trades->privateHistory('BTC-USD', limit: 100);

// Fills belonging to an individual venue order.
$fills = $trades->fills('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
```

All three methods return the response payload from Revolut X. Public and private history include pagination information in `metadata.next_cursor` when another page is available.

### Trading

Trading calls require an authenticated client. Supply your own UUID for every `clientOrderId`; this is important for safely locating an order when a network reply is lost.

```php
$trading = $client->trading();

// Place a limit order.
$placed = $trading->place(
    clientOrderId: '3fa85f64-5717-4562-b3fc-2c963f66afa6',
    symbol: 'BTC-USD',
    side: 'buy',
    configuration: [
        'limit' => [
            'base_size' => '0.001',
            'price' => '95000',
            'execution_instructions' => ['post_only'],
        ],
    ],
);

// A market order uses a market configuration instead.
$marketOrder = $trading->place(
    clientOrderId: '9a0ea5d1-74a5-4cb6-9a5b-d0254ec2c6b1',
    symbol: 'BTC-USD',
    side: 'sell',
    configuration: ['market' => ['base_size' => '0.001']],
);

// Replace only the fields that change. Revolut X assigns the replacement a new venue ID.
$replaced = $trading->replace(
    venueOrderId: '7a52e92e-8639-4fe1-abaa-68d3a2d5234b',
    clientOrderId: 'b7193799-86e5-4062-8d0d-3ed4b980239a',
    changes: ['price' => '96000'],
);

$trading->cancel('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
$trading->cancelAll();
```

`place()` sends the `configuration` object as provided, so it can represent every order type supported by the current Revolut X API. `replace()` likewise forwards the permitted replacement fields without narrowing the exchange contract.

## Authentication

The client signs authenticated requests with Ed25519. The signature covers the exact string specified by Revolut X:

```text
{timestamp}{HTTP method}{path}{query string}{body}
```

It then sends `X-Revx-API-Key`, `X-Revx-Timestamp`, and `X-Revx-Signature` headers. The private key remains inside your process; it is never sent over the network.

Generate an Ed25519 key pair with OpenSSL, then register its public key in Revolut X before creating an API key:

```bash
openssl genpkey -algorithm ed25519 -out private.pem
openssl pkey -in private.pem -pubout -out public.pem
```

Treat `private.pem` and the API key as production secrets. Restrict the key file to its owner and rotate the API key immediately if it is exposed.

## Errors

All library exceptions extend `Revx\Exception\RevxException`.

- `AuthenticationException` — credentials are missing or the private key cannot be used.
- `ApiException` — the exchange returned a non-2xx response. It exposes `status`, `response`, and `retryAfter` when provided.
- `NetworkException` — cURL could not complete the request.

```php
use Revx\Exception\ApiException;

try {
    $balances = $client->account()->balances();
} catch (ApiException $e) {
    if ($e->status === 429) {
        // Retry only after $e->retryAfter milliseconds when it is supplied.
    }

    throw $e;
}
```

## Scope

This package exposes:

- public currencies, pairs, tickers, candles, and order books;
- authenticated balances and order lookups;
- public and authenticated trade history, plus per-order fills;
- order placement, replacement, cancellation, and cancellation of all active orders.

Trading operations send real instructions to the exchange. Validate application inputs, use least-privilege API keys, and treat order placement and cancellation as production-impacting actions.

## Testing

```bash
composer test
```

The test suite uses an in-memory HTTP transport, so it makes no network calls and needs no real Revolut X credentials.

## License

MIT. See [LICENSE](LICENSE).
