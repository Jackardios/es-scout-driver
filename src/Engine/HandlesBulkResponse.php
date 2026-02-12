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

        $failedDocuments = $this->extractFailedDocuments($response);

        if ($failedDocuments === []) {
            return;
        }

        $mode = $this->getBulkFailureMode();

        match ($mode) {
            'ignore' => null,
            'log' => $this->logBulkFailures($failedDocuments),
            default => throw new BulkOperationException($failedDocuments),
        };
    }

    private function getBulkFailureMode(): string
    {
        if (!function_exists('config')) {
            return 'exception';
        }

        try {
            return config('elastic.scout.bulk_failure_mode', 'exception') ?? 'exception';
        } catch (\Throwable) {
            return 'exception';
        }
    }

    private function extractFailedDocuments(array $response): array
    {
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

        return $failedDocuments;
    }

    private function logBulkFailures(array $failedDocuments): void
    {
        if (!function_exists('logger')) {
            return;
        }

        try {
            logger()->error('Elasticsearch bulk operation partially failed', [
                'failed_count' => count($failedDocuments),
                'documents' => $failedDocuments,
            ]);
        } catch (\Throwable) {
        }
    }
}
