<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Jobs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Exceptions\BulkOperationException;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RemoveFromSearchTest extends TestCase
{
    #[Test]
    public function it_throws_exception_for_empty_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create RemoveFromSearch job with empty collection');

        new RemoveFromSearch(new Collection());
    }

    #[Test]
    public function it_extracts_index_name_from_models(): void
    {
        $model = $this->createModelWithScout('1', 'books');

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame('books', $job->indexName);
    }

    #[Test]
    public function it_extracts_document_ids_from_models(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'books');
        $model3 = $this->createModelWithScout('3', 'books');

        $job = new RemoveFromSearch(new Collection([$model1, $model2, $model3]));

        $this->assertSame(['1', '2', '3'], $job->documentIds);
    }

    #[Test]
    public function it_extracts_routing_from_models_with_searchable_routing(): void
    {
        $model1 = $this->createModelWithRouting('1', 'books', 'route-a');
        $model2 = $this->createModelWithScout('2', 'books');
        $model3 = $this->createModelWithRouting('3', 'books', 'route-b');

        $job = new RemoveFromSearch(new Collection([$model1, $model2, $model3]));

        $this->assertSame([
            '1' => 'route-a',
            '3' => 'route-b',
        ], $job->routing);
    }

    #[Test]
    public function it_handles_models_without_searchable_routing_method(): void
    {
        $model = $this->createModelWithScout('1', 'books');

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame([], $job->routing);
    }

    #[Test]
    public function it_handles_models_with_null_routing(): void
    {
        $model = $this->createModelWithRouting('1', 'books', null);

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame([], $job->routing);
    }

    #[Test]
    public function it_uses_first_model_index_name(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'authors');

        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $this->assertSame('books', $job->indexName);
    }

    #[Test]
    public function it_converts_scout_key_to_string(): void
    {
        $model = $this->createModelWithIntegerKey(42, 'books');

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame(['42'], $job->documentIds);
    }

    #[Test]
    public function it_converts_routing_to_string(): void
    {
        $model = $this->createModelWithIntegerRouting(1, 'books', 123);

        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame(['1' => '123'], $job->routing);
    }

    #[Test]
    public function handle_bulk_response_does_not_throw_when_no_errors(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = ['errors' => false];

        $this->invokeHandleBulkResponse($job, $response);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handle_bulk_response_does_not_throw_when_errors_key_missing(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = [];

        $this->invokeHandleBulkResponse($job, $response);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function handle_bulk_response_throws_bulk_operation_exception_on_errors(): void
    {
        $model = $this->createModelWithScout('1', 'books');
        $job = new RemoveFromSearch(new Collection([$model]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'error' => ['type' => 'not_found', 'reason' => 'Document not found'],
                    ],
                ],
            ],
        ];

        $this->expectException(BulkOperationException::class);

        $this->invokeHandleBulkResponse($job, $response);
    }

    #[Test]
    public function handle_bulk_response_collects_all_failed_documents(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'books');
        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'error' => ['type' => 'error1', 'reason' => 'Reason 1'],
                    ],
                ],
                [
                    'delete' => [
                        '_id' => '2',
                        '_index' => 'books',
                        'error' => ['type' => 'error2', 'reason' => 'Reason 2'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($job, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();
            $this->assertCount(2, $failedDocs);
            $this->assertSame('1', $failedDocs[0]['id']);
            $this->assertSame('2', $failedDocs[1]['id']);
        }
    }

    #[Test]
    public function handle_bulk_response_ignores_successful_items(): void
    {
        $model1 = $this->createModelWithScout('1', 'books');
        $model2 = $this->createModelWithScout('2', 'books');
        $job = new RemoveFromSearch(new Collection([$model1, $model2]));

        $response = [
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_id' => '1',
                        '_index' => 'books',
                        'result' => 'deleted',
                    ],
                ],
                [
                    'delete' => [
                        '_id' => '2',
                        '_index' => 'books',
                        'error' => ['type' => 'error', 'reason' => 'Failed'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeHandleBulkResponse($job, $response);
            $this->fail('Expected BulkOperationException');
        } catch (BulkOperationException $e) {
            $failedDocs = $e->getFailedDocuments();
            $this->assertCount(1, $failedDocs);
            $this->assertSame('2', $failedDocs[0]['id']);
        }
    }

    #[Test]
    public function job_properties_are_public(): void
    {
        $model = $this->createModelWithRouting('1', 'books', 'route-a');
        $job = new RemoveFromSearch(new Collection([$model]));

        $this->assertSame('books', $job->indexName);
        $this->assertSame(['1'], $job->documentIds);
        $this->assertSame(['1' => 'route-a'], $job->routing);
    }

    private function invokeHandleBulkResponse(RemoveFromSearch $job, array $response): void
    {
        $method = new ReflectionMethod(RemoveFromSearch::class, 'handleBulkResponse');
        $method->invoke($job, $response);
    }

    private function createModelWithScout(string $id, string $indexName): Model
    {
        return new class ($id, $indexName) extends Model {
            private string $scoutId;
            private string $scoutIndex;

            public function __construct(string $id, string $indexName)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }
        };
    }

    private function createModelWithRouting(string $id, string $indexName, ?string $routing): Model
    {
        return new class ($id, $indexName, $routing) extends Model {
            private string $scoutId;
            private string $scoutIndex;
            private ?string $scoutRouting;

            public function __construct(string $id, string $indexName, ?string $routing)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutRouting = $routing;
            }

            public function getScoutKey(): string
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): ?string
            {
                return $this->scoutRouting;
            }
        };
    }

    private function createModelWithIntegerKey(int $id, string $indexName): Model
    {
        return new class ($id, $indexName) extends Model {
            private int $scoutId;
            private string $scoutIndex;

            public function __construct(int $id, string $indexName)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
            }

            public function getScoutKey(): int
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }
        };
    }

    private function createModelWithIntegerRouting(int $id, string $indexName, int $routing): Model
    {
        return new class ($id, $indexName, $routing) extends Model {
            private int $scoutId;
            private string $scoutIndex;
            private int $scoutRouting;

            public function __construct(int $id, string $indexName, int $routing)
            {
                $this->scoutId = $id;
                $this->scoutIndex = $indexName;
                $this->scoutRouting = $routing;
            }

            public function getScoutKey(): int
            {
                return $this->scoutId;
            }

            public function searchableAs(): string
            {
                return $this->scoutIndex;
            }

            public function searchableRouting(): int
            {
                return $this->scoutRouting;
            }
        };
    }
}
