<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch as ElasticsearchResponse;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use Jackardios\EsScoutDriver\Support\ConfigOption;

/** @internal */
trait SendsBulkRequests
{
    /** @param list<array<string, mixed>> $body */
    private function sendBulk(Client $client, array $body, bool $refresh): void
    {
        $failureMode = ConfigOption::oneOf('elastic.scout.bulk_failure_mode', ['exception', 'log', 'ignore'], 'exception');

        $params = ['body' => $body];

        if ($refresh) {
            $params['refresh'] = 'true';
        }

        /** @var ElasticsearchResponse $response */
        $response = $client->bulk($params);
        $failedDocuments = $this->extractFailedDocuments($response->asArray());

        if ($failedDocuments === [] || $failureMode === 'ignore') {
            return;
        }

        if ($failureMode === 'log') {
            logger()->error('Elasticsearch bulk operation partially failed', [
                'failed_count' => count($failedDocuments),
                'documents' => $failedDocuments,
            ]);

            return;
        }

        throw new BulkOperationException($failedDocuments);
    }

    /**
     * @param array<string, mixed> $response
     * @return list<array{action: string, index: ?string, id: ?string, error: mixed}>
     */
    private function extractFailedDocuments(array $response): array
    {
        if (($response['errors'] ?? false) !== true) {
            return [];
        }

        $failedDocuments = [];

        foreach ($response['items'] ?? [] as $item) {
            $action = (string) array_key_first($item);
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
}
