<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

use RuntimeException;

final class BulkOperationException extends RuntimeException
{
    /** @param array<int, array<string, mixed>> $failedDocuments */
    public function __construct(private readonly array $failedDocuments, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::describe($failedDocuments));
    }

    /** @return array<int, array<string, mixed>> */
    public function getFailedDocuments(): array
    {
        return $this->failedDocuments;
    }

    /** @param array<int, array<string, mixed>> $failedDocuments */
    private static function describe(array $failedDocuments): string
    {
        $message = sprintf('Bulk operation failed for %d document(s)', count($failedDocuments));
        $error = $failedDocuments[0]['error'] ?? null;

        if (is_array($error) && isset($error['type'])) {
            $message .= sprintf(', the first with %s: %s', $error['type'], $error['reason'] ?? 'no reason given');
        }

        return rtrim($message, '.') . '.';
    }
}
