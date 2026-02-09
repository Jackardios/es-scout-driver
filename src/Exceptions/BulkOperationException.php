<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

use RuntimeException;

class BulkOperationException extends RuntimeException
{
    /** @var array<int, array<string, mixed>> */
    private array $failedDocuments;

    /** @param array<int, array<string, mixed>> $failedDocuments */
    public function __construct(array $failedDocuments, string $message = '')
    {
        $this->failedDocuments = $failedDocuments;

        if ($message === '') {
            $count = count($failedDocuments);
            $message = sprintf('Bulk operation failed for %d document(s)', $count);
        }

        parent::__construct($message);
    }

    /** @return array<int, array<string, mixed>> */
    public function getFailedDocuments(): array
    {
        return $this->failedDocuments;
    }
}
