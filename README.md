# revx-php

An unofficial, vibe-coded, PHP 8.4+ client for the [Revolut X REST API](https://developer.revolut.com/docs/api/revolut-x-crypto-exchange).
It covers public market data and signed read-only account data, with no framework dependency and no trading actions.

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

This package deliberately exposes only:

- public currencies, pairs, tickers, candles, and order books;
- authenticated balances and order lookups.

It does not place, amend, or cancel orders. That keeps the package suitable for applications that need market visibility and account reporting without granting a library path to execute trades.

## Testing

```bash
composer test
```

The test suite uses an in-memory HTTP transport, so it makes no network calls and needs no real Revolut X credentials.

## License

MIT. See [LICENSE](LICENSE).
