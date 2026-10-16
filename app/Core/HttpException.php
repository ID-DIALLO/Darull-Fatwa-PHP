<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Erreur HTTP « attendue » (403, 404, 419, 429, 503…) affichée avec la page d'erreur traduite.
 */
class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly int $status,
        string $message = '',
        private readonly array $headers = [],
    ) {
        parent::__construct($message !== '' ? $message : 'HTTP ' . $status, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
