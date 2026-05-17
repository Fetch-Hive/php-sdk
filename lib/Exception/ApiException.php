<?php

declare(strict_types=1);

namespace FetchHive\Sdk\Exception;

use RuntimeException;

/**
 * Thrown when the Fetch Hive API returns a non-2xx response.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $body,
        string $message = ''
    ) {
        parent::__construct(
            $message !== '' ? $message : "FetchHive API error {$statusCode}: {$body}",
            $statusCode
        );
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
