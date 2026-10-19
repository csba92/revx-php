<?php

declare(strict_types=1);

namespace Revx\Auth;

use Revx\Exception\AuthenticationException;

final readonly class RequestSigner
{
    private string $secretKey;

    public function __construct(string $privateKeyPem)
    {
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw new AuthenticationException('The supplied private key is not a valid PEM private key.');
        }

        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new AuthenticationException('Unable to inspect the supplied private key.');
        }

        $seed = $details['ed25519']['priv_key'] ?? null;
        if (!is_string($seed) || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            throw new AuthenticationException('The supplied private key must use Ed25519.');
        }

        $keyPair = sodium_crypto_sign_seed_keypair($seed);
        $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
    }

    public function sign(string $timestamp, string $method, string $path, string $query = '', string $body = ''): string
    {
        $message = $timestamp . strtoupper($method) . $path . $query . $body;

        return base64_encode(sodium_crypto_sign_detached($message, $this->secretKey));
    }
}
