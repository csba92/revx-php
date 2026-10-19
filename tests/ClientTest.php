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
