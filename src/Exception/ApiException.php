<?php

declare(strict_types=1);

namespace Revx\Exception;

final class ApiException extends RevxException
{
    public function __construct(string $message, int $status, array|null $response = null, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $status, $response);
    }
}
