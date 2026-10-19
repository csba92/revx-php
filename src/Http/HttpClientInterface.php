<?php

declare(strict_types=1);

namespace Revx\Http;

interface HttpClientInterface
{
    /** @param array<string, string> $headers */
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout): Response;
}
