<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use ArrayObject;
use Jackardios\EsScoutDriver\Engine\EngineInterface;
use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Generator;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\EsScoutDriver\Search\SearchCursor;
use Jackardios\EsScoutDriver\Sort\SortInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SearchCursorTest extends TestCase
{
    #[Test]
    public function it_implements_iterator_aggregate(): void
    {
        $reflection = new \ReflectionClass(SearchCursor::class);

        $this->assertTrue($reflection->implementsInterface(\IteratorAggregate::class));
    }

    #[Test]
    public function it_has_required_constructor_parameters(): void
    {
        $reflection = new \ReflectionClass(SearchCursor::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertSame('builder', $params[0]->getName());
        $this->assertSame('chunkSize', $params[1]->getName());
        $this->assertSame('keepAlive', $params[2]->getName());

        $this->assertSame(1000, $params[1]->getDefaultValue());
        $this->assertSame('5m', $params[2]->getDefaultValue());
    }

    #[Test]
    public function it_throws_for_non_positive_chunk_size(): void
    {
        $builder = new SearchCursorTestBuilder(
            engine: $this->createMock(EngineInterface::class),
            indexNames: ['Book' => 'books'],
            sort: [],
            searchAfter: null,
            executedRequests: new ArrayObject(),
            metrics: new SearchCursorTestBuilderMetrics(),
            executor: fn() => [],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chunkSize must be greater than 0.');

        new SearchCursor($builder, 0, '1m');
    }

    #[Test]
    public function it_paginates_with_search_after_and_applies_shard_doc_sort(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '1m')
            ->willReturn('pit-1');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-1');

        $executedRequests = new ArrayObject();
        $metrics = new SearchCursorTestBuilderMetrics();

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [],
            searchAfter: ['seed'],
            executedRequests: $executedRequests,
            metrics: $metrics,
            executor: function (SearchCursorTestBuilder $builder): array {
                $searchAfter = $builder->getSearchAfter();

                if ($searchAfter === ['seed']) {
                    return [
                        $this->rawHit('1', ['a']),
                        $this->rawHit('2', ['b']),
                    ];
                }

                if ($searchAfter === ['b']) {
                    return [
                        $this->rawHit('3', ['c']),
                    ];
                }

                return [];
            },
        );

        $cursor = new SearchCursor($builder, 2, '1m');

        $ids = [];
        foreach ($cursor as $hit) {
            $ids[] = $hit->documentId;
        }

        $this->assertSame(['1', '2', '3'], $ids);
        $this->assertCount(2, $executedRequests);
        $this->assertSame(2, $metrics->sortCalls);

        /** @var array<string, mixed> $firstRequest */
        $firstRequest = $executedRequests[0];
        $this->assertSame(['id' => 'pit-1', 'keep_alive' => '1m'], $firstRequest['pit']);
        $this->assertSame(2, $firstRequest['size']);
        $this->assertSame(0, $firstRequest['from']);
        $this->assertSame(['seed'], $firstRequest['search_after']);
        $this->assertSame([['_shard_doc' => 'asc']], $firstRequest['sort']);

        /** @var array<string, mixed> $secondRequest */
        $secondRequest = $executedRequests[1];
        $this->assertSame(['id' => 'pit-1', 'keep_alive' => '1m'], $secondRequest['pit']);
        $this->assertSame(2, $secondRequest['size']);
        $this->assertSame(0, $secondRequest['from']);
        $this->assertSame(['b'], $secondRequest['search_after']);
        $this->assertSame([['_shard_doc' => 'asc']], $secondRequest['sort']);
    }

    #[Test]
    public function it_does_not_apply_shard_doc_sort_when_already_present(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '5m')
            ->willReturn('pit-2');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-2');

        $executedRequests = new ArrayObject();
        $metrics = new SearchCursorTestBuilderMetrics();

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [['_shard_doc' => 'asc']],
            searchAfter: null,
            executedRequests: $executedRequests,
            metrics: $metrics,
            executor: fn() => [$this->rawHit('1', ['done'])],
        );

        $cursor = new SearchCursor($builder, 2, '5m');

        $ids = [];
        foreach ($cursor as $hit) {
            $ids[] = $hit->documentId;
        }

        $this->assertSame(['1'], $ids);
        $this->assertCount(1, $executedRequests);
        $this->assertSame(0, $metrics->sortCalls);

        /** @var array<string, mixed> $request */
        $request = $executedRequests[0];
        $this->assertSame([['_shard_doc' => 'asc']], $request['sort']);
        $this->assertNull($request['from']);
    }

    #[Test]
    public function it_closes_point_in_time_when_search_throws(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '30s')
            ->willReturn('pit-3');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-3');

        $executedRequests = new ArrayObject();
        $metrics = new SearchCursorTestBuilderMetrics();

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [],
            searchAfter: null,
            executedRequests: $executedRequests,
            metrics: $metrics,
            executor: static function (): array {
                throw new RuntimeException('search failed');
            },
        );

        $cursor = new SearchCursor($builder, 1, '30s');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('search failed');

        foreach ($cursor as $_) {
            // No-op: iterating triggers execution.
        }
    }

    #[Test]
    public function it_updates_point_in_time_id_from_response_between_requests(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '1m')
            ->willReturn('pit-1');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-2');

        $executedRequests = new ArrayObject();
        $metrics = new SearchCursorTestBuilderMetrics();
        $requestCount = 0;

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [['_shard_doc' => 'asc']],
            searchAfter: null,
            executedRequests: $executedRequests,
            metrics: $metrics,
            executor: function () use (&$requestCount): array {
                $requestCount++;

                if ($requestCount === 1) {
                    return [
                        'pit_id' => 'pit-2',
                        'hits' => [
                            $this->rawHit('1', ['a']),
                        ],
                    ];
                }

                return ['hits' => []];
            },
        );

        $cursor = new SearchCursor($builder, 1, '1m');
        $hits = iterator_to_array($cursor);

        $this->assertCount(1, $hits);
        $this->assertCount(2, $executedRequests);
        $this->assertSame('pit-1', $executedRequests[0]['pit']['id']);
        $this->assertSame('pit-2', $executedRequests[1]['pit']['id']);
    }

    #[Test]
    public function it_does_not_mask_search_error_when_close_fails(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '1m')
            ->willReturn('pit-1');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-1')
            ->willThrowException(new RuntimeException('close failed'));

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [],
            searchAfter: null,
            executedRequests: new ArrayObject(),
            metrics: new SearchCursorTestBuilderMetrics(),
            executor: static function (): array {
                throw new RuntimeException('search failed');
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('search failed');

        foreach (new SearchCursor($builder, 1, '1m') as $_) {
            // No-op.
        }
    }

    #[Test]
    public function it_throws_close_error_when_no_primary_error_exists(): void
    {
        $engine = $this->createMock(EngineInterface::class);
        $engine->expects($this->once())
            ->method('openPointInTime')
            ->with('books', '1m')
            ->willReturn('pit-1');
        $engine->expects($this->once())
            ->method('closePointInTime')
            ->with('pit-1')
            ->willThrowException(new RuntimeException('close failed'));

        $builder = new SearchCursorTestBuilder(
            engine: $engine,
            indexNames: ['Book' => 'books'],
            sort: [],
            searchAfter: null,
            executedRequests: new ArrayObject(),
            metrics: new SearchCursorTestBuilderMetrics(),
            executor: fn() => [],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('close failed');

        foreach (new SearchCursor($builder, 1, '1m') as $_) {
            // No-op.
        }
    }

    private function rawHit(string $id, array $sort): array
    {
        return [
            '_index' => 'books',
            '_id' => $id,
            '_source' => ['id' => $id],
            'sort' => $sort,
        ];
    }
}

final class SearchCursorTestBuilderMetrics
{
    public int $sortCalls = 0;
}

final class SearchCursorTestBuilder extends SearchBuilder
{
    private EngineInterface $engine;

    /** @var array<string, string> */
    private array $indexNames;

    private ?array $searchAfter;

    private array $sort;

    private ?array $pointInTime = null;

    private ?int $size = null;

    private ?int $from = null;

    /** @var ArrayObject<int, array<string, mixed>> */
    private ArrayObject $executedRequests;

    /** @var callable(self): array<int, array<string, mixed>>|array{hits: array<int, array<string, mixed>>, pit_id?: string} */
    private mixed $executor;

    private SearchCursorTestBuilderMetrics $metrics;

    /**
     * @param array<string, string> $indexNames
     * @param array<int, array<string, mixed>> $sort
     * @param callable(self): array<int, array<string, mixed>>|array{hits: array<int, array<string, mixed>>, pit_id?: string} $executor
     * @param ArrayObject<int, array<string, mixed>> $executedRequests
     */
    public function __construct(
        EngineInterface $engine,
        array $indexNames,
        array $sort,
        ?array $searchAfter,
        ArrayObject $executedRequests,
        SearchCursorTestBuilderMetrics $metrics,
        callable $executor,
    ) {
        $this->engine = $engine;
        $this->indexNames = $indexNames;
        $this->sort = $sort;
        $this->searchAfter = $searchAfter;
        $this->executedRequests = $executedRequests;
        $this->metrics = $metrics;
        $this->executor = $executor;
    }

    public function __clone(): void
    {
        // Keep test doubles isolated from SearchBuilder deep-clone internals.
    }

    public function getIndexNames(): array
    {
        return $this->indexNames;
    }

    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    public function getSearchAfter(): ?array
    {
        return $this->searchAfter;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function pointInTime(string $id, ?string $keepAlive = null): self
    {
        $this->pointInTime = ['id' => $id];

        if ($keepAlive !== null) {
            $this->pointInTime['keep_alive'] = $keepAlive;
        }

        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function sort(
        string|SortInterface $field,
        SortOrder|string $direction = 'asc',
        string|int|float|bool|null $missing = null,
        ?string $mode = null,
        ?string $unmappedType = null,
    ): self {
        $this->metrics->sortCalls++;

        if ($field instanceof SortInterface) {
            $this->sort[] = $field->toArray();
            return $this;
        }

        $order = $direction instanceof SortOrder ? $direction->value : $direction;
        $this->sort[] = [$field => $order];

        return $this;
    }

    public function from(int $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function searchAfter(array $searchAfter): self
    {
        $this->searchAfter = $searchAfter;
        return $this;
    }

    public function execute(): SearchResult
    {
        $this->executedRequests->append([
            'pit' => $this->pointInTime,
            'size' => $this->size,
            'from' => $this->from,
            'search_after' => $this->searchAfter,
            'sort' => $this->sort,
        ]);

        $response = ($this->executor)($this);
        $hits = array_is_list($response) ? $response : ($response['hits'] ?? []);

        $rawResult = [
            'hits' => [
                'total' => ['value' => count($hits)],
                'hits' => $hits,
            ],
        ];

        if (!array_is_list($response) && isset($response['pit_id']) && is_string($response['pit_id'])) {
            $rawResult['pit_id'] = $response['pit_id'];
        }

        return new SearchResult($rawResult);
    }
}
