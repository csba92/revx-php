<?php

declare(strict_types=1);

namespace Revx\Tests;

use PHPUnit\Framework\TestCase;
use Revx\Client;
use Revx\Http\HttpClientInterface;
use Revx\Http\Response;

final class ClientTest extends TestCase
{
    public function testItBuildsAPublicTickerRequest(): void
    {
        $http = new RecordingHttpClient(new Response(200, '{"data":[{"symbol":"BTC/USD"}],"metadata":{"timestamp":1}}'));
        $client = new Client(http: $http);

        self::assertSame(['symbol' => 'BTC/USD'], $client->market()->ticker('BTC-USD'));
        self::assertSame('https://revx.revolut.com/api/1.0/public/tickers?symbols=BTC-USD', $http->calls[0]['url']);
        self::assertArrayNotHasKey('X-Revx-Signature', $http->calls[0]['headers']);
    }

    public function testItSignsAuthenticatedRequests(): void
    {
        $key = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem));
        $details = openssl_pkey_get_details($key);

        $http = new RecordingHttpClient(new Response(200, '[]'));
        $client = new Client('api-key', $pem, $http);
        self::assertSame([], $client->account()->balances());

        $headers = $http->calls[0]['headers'];
        $message = $headers['X-Revx-Timestamp'] . 'GET/api/1.0/balances';
        self::assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode($headers['X-Revx-Signature'], true),
            $message,
            $details['ed25519']['pub_key'],
        ));
    }

    public function testItPlacesAndSignsAnOrder(): void
    {
        $key = @openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519]);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem));
        $details = openssl_pkey_get_details($key);

        $http = new RecordingHttpClient(new Response(200, '{"data":[]}'));
        $client = new Client('api-key', $pem, $http);
        $client->trading()->place(
            '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'BTC-USD',
            'buy',
            ['limit' => ['base_size' => '0.001', 'price' => '95000']],
        );

        $call = $http->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('https://revx.revolut.com/api/1.0/orders', $call['url']);
        self::assertSame('application/json', $call['headers']['Content-Type']);
        self::assertJsonStringEqualsJsonString(
            '{"client_order_id":"3fa85f64-5717-4562-b3fc-2c963f66afa6","symbol":"BTC-USD","side":"buy","order_configuration":{"limit":{"base_size":"0.001","price":"95000"}}}',
            (string) $call['body'],
        );

        $message = $call['headers']['X-Revx-Timestamp'] . 'POST/api/1.0/orders' . $call['body'];
        self::assertTrue(sodium_crypto_sign_verify_detached(
            base64_decode($call['headers']['X-Revx-Signature'], true),
            $message,
            $details['ed25519']['pub_key'],
        ));

        $client->trading()->replace(
            '7a52e92e-8639-4fe1-abaa-68d3a2d5234b',
            'b7193799-86e5-4062-8d0d-3ed4b980239a',
            ['price' => '96000'],
        );
        $client->trading()->cancel('7a52e92e-8639-4fe1-abaa-68d3a2d5234b');
        $client->trading()->cancelAll();

        self::assertSame(['POST', 'PUT', 'DELETE', 'DELETE'], array_column($http->calls, 'method'));
        self::assertSame(
            'https://revx.revolut.com/api/1.0/orders/7a52e92e-8639-4fe1-abaa-68d3a2d5234b',
            $http->calls[1]['url'],
        );
        self::assertJsonStringEqualsJsonString(
            '{"client_order_id":"b7193799-86e5-4062-8d0d-3ed4b980239a","price":"96000"}',
            (string) $http->calls[1]['body'],
        );
    }
}

final class RecordingHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    public function __construct(private readonly Response $response)
    {
    }

    public function request(string $method, string $url, array $headers, ?string $body, int $timeout): Response
    {
        $this->calls[] = compact('method', 'url', 'headers', 'body');

        return $this->response;
    }
}
