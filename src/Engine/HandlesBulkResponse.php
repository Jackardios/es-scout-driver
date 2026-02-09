<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;

trait HandlesBulkResponse
{
    /** @param array<string, mixed> $response */
    protected function handleBulkResponse(array $response): void
    {
        if (!isset($response['errors']) || $response['errors'] !== true) {
            return;
        }

        $failedDocuments = [];

        foreach ($response['items'] ?? [] as $item) {
            $action = array_key_first($item);
            $result = $item[$action];

            if (isset($result['error'])) {
                $failedDocuments[] = [
                    'action' => $action,
                    'index' => $result['_index'] ?? null,
                    'id' => $result['_id'] ?? null,
                    'error' => $result['error'],
                ];
            }
        }

        if ($failedDocuments !== []) {
            throw new BulkOperationException($failedDocuments);
        }
    }
}
