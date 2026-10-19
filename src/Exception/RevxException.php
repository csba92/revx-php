<?php

declare(strict_types=1);

namespace Revx\Exception;

use RuntimeException;

class RevxException extends RuntimeException
{
    /** @param array<string, mixed>|list<mixed>|null $response */
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly array|null $response = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
