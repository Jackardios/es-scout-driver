<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use Jackardios\EsScoutDriver\Engine\ModelResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModelResolverTest extends TestCase
{
    protected function setUp(): void
    {
        FakeBookModel::resetFakeState();
        FakeAuthorModel::resetFakeState();
        FakeSoftDeleteBookModel::resetFakeState();
    }

    #[Test]
    public function resolve_loads_pending_models_once_and_uses_cache(): void
    {
        FakeBookModel::seedRecords(['1', '2']);

        $resolver = new ModelResolver(new AliasRegistry(), [
            ['_index' => 'books', '_id' => '1'],
            ['_index' => 'books', '_id' => '2'],
            ['_index' => 'books', '_id' => '1'],
        ]);
        $resolver->registerIndex(
            indexName: 'books',
            modelClass: FakeBookModel::class,
            relations: ['author'],
        );

        $resolve = $resolver->createResolver();
        $resolvedFirst = $resolve('books', '1');
        $resolvedSecond = $resolve('books', '2');
        $resolvedMissing = $resolve('books', '404');

        $this->assertInstanceOf(FakeBookModel::class, $resolvedFirst);
        $this->assertInstanceOf(FakeBookModel::class, $resolvedSecond);
        $this->assertNull($resolvedMissing);
        $this->assertSame('1', (string) $resolvedFirst?->getScoutKey());
        $this->assertSame('2', (string) $resolvedSecond?->getScoutKey());

        $this->assertSame(1, FakeBookModel::$newQueryCalls);
        $this->assertSame(1, FakeBookModel::$getCalls);
        $this->assertSame([
            ['field' => 'id', 'ids' => ['1', '2']],
        ], FakeBookModel::$whereInCalls);
        $this->assertSame([['author']], FakeBookModel::$withCalls);

        $cachedModels = $resolver->getCachedModels('books');
        $this->assertArrayHasKey('1', $cachedModels);
        $this->assertArrayHasKey('2', $cachedModels);
    }

    #[Test]
    public function resolve_applies_query_and_collection_callbacks_with_raw_result(): void
    {
        FakeBookModel::seedRecords(['1', '2']);

        $seenRawResult = null;
        $queryCallbackCalls = 0;
        $rawResult = ['took' => 5];

        $resolver = new ModelResolver(
            aliasRegistry: new AliasRegistry(),
            rawHits: [
                ['_index' => 'books', '_id' => '1'],
                ['_index' => 'books', '_id' => '2'],
            ],
            rawResult: $rawResult,
        );
        $resolver->registerIndex(
            indexName: 'books',
            modelClass: FakeBookModel::class,
            queryCallbacks: [
                function (object $query, array $receivedRawResult) use (&$queryCallbackCalls, &$seenRawResult): void {
                    $queryCallbackCalls++;
                    $seenRawResult = $receivedRawResult;
                },
            ],
            collectionCallbacks: [
                fn(EloquentCollection $models): EloquentCollection => $models
                    ->filter(static fn(Model $model): bool => (string) $model->getScoutKey() === '2')
                    ->values(),
            ],
        );

        $resolve = $resolver->createResolver();
        $this->assertNull($resolve('books', '1'));
        $resolved = $resolve('books', '2');

        $this->assertInstanceOf(FakeBookModel::class, $resolved);
        $this->assertSame('2', (string) $resolved?->getScoutKey());
        $this->assertSame(1, $queryCallbackCalls);
        $this->assertSame($rawResult, $seenRawResult);
    }

    #[Test]
    public function resolve_uses_with_trashed_only_for_soft_delete_models_when_requested(): void
    {
        FakeSoftDeleteBookModel::seedRecords(['1']);

        $resolver = new ModelResolver(new AliasRegistry(), [['_index' => 'books', '_id' => '1']]);
        $resolver->registerIndex('books', FakeSoftDeleteBookModel::class, withTrashed: true);

        $resolvedSoftDeleteModel = $resolver->createResolver()('books', '1');

        $this->assertInstanceOf(FakeSoftDeleteBookModel::class, $resolvedSoftDeleteModel);
        $this->assertSame(1, FakeSoftDeleteBookModel::$withTrashedCalls);
        $this->assertSame(0, FakeSoftDeleteBookModel::$newQueryCalls);

        FakeBookModel::seedRecords(['1']);

        $resolver = new ModelResolver(new AliasRegistry(), [['_index' => 'books', '_id' => '1']]);
        $resolver->registerIndex('books', FakeBookModel::class, withTrashed: true);

        $resolvedRegularModel = $resolver->createResolver()('books', '1');

        $this->assertInstanceOf(FakeBookModel::class, $resolvedRegularModel);
        $this->assertSame(0, FakeBookModel::$withTrashedCalls);
        $this->assertSame(1, FakeBookModel::$newQueryCalls);
    }

    #[Test]
    public function preload_all_collects_ids_from_hits_and_suggestions(): void
    {
        FakeBookModel::seedRecords(['1', '2', '3']);
        FakeAuthorModel::seedRecords(['10']);

        $resolver = new ModelResolver(
            aliasRegistry: new AliasRegistry(),
            rawHits: [
                ['_index' => 'books', '_id' => '1'],
                ['_index' => 'authors', '_id' => '10'],
            ],
            rawSuggestions: [
                'title-suggest' => [
                    [
                        'options' => [
                            ['_index' => 'books', '_id' => '2'],
                            ['_index' => 'books', '_id' => '2'],
                        ],
                    ],
                ],
            ],
        );
        $resolver->registerIndex('books', FakeBookModel::class);
        $resolver->registerIndex('authors', FakeAuthorModel::class);

        $resolver->preloadAll();

        $cachedBooks = $resolver->getCachedModels('books');
        $cachedAuthors = $resolver->getCachedModels('authors');

        $this->assertArrayHasKey('1', $cachedBooks);
        $this->assertArrayHasKey('2', $cachedBooks);
        $this->assertArrayNotHasKey('3', $cachedBooks);
        $this->assertArrayHasKey('10', $cachedAuthors);

        $this->assertSame(1, FakeBookModel::$newQueryCalls);
        $this->assertSame(1, FakeAuthorModel::$newQueryCalls);
        $this->assertSame([
            ['field' => 'id', 'ids' => ['1', '2']],
        ], FakeBookModel::$whereInCalls);
        $this->assertSame([
            ['field' => 'id', 'ids' => ['10']],
        ], FakeAuthorModel::$whereInCalls);
    }

    #[Test]
    public function with_raw_data_preserves_registered_indices(): void
    {
        FakeBookModel::seedRecords(['42']);

        $resolver = new ModelResolver(new AliasRegistry());
        $resolver->registerIndex('books', FakeBookModel::class);

        $newResolver = $resolver->withRawData([['_index' => 'books', '_id' => '42']]);
        $resolved = $newResolver->createResolver()('books', '42');

        $this->assertInstanceOf(FakeBookModel::class, $resolved);
        $this->assertSame('42', (string) $resolved?->getScoutKey());
    }

    #[Test]
    public function resolve_returns_null_for_unknown_index_without_querying_models(): void
    {
        FakeBookModel::seedRecords(['1']);

        $resolver = new ModelResolver(new AliasRegistry(), [['_index' => 'unknown', '_id' => '1']]);
        $resolver->registerIndex('books', FakeBookModel::class);

        $resolved = $resolver->createResolver()('unknown', '1');

        $this->assertNull($resolved);
        $this->assertSame(0, FakeBookModel::$newQueryCalls);
    }

    #[Test]
    public function resolve_hydrates_model_with_hit_metadata(): void
    {
        FakeBookModel::seedRecords(['1', '2']);

        $resolver = new ModelResolver(new AliasRegistry(), [
            ['_index' => 'books', '_id' => '1', '_score' => 1.5, '_routing' => 'tenant-1'],
            ['_index' => 'books', '_id' => '2', '_score' => 0.8, '_source' => ['title' => 'Test']],
        ]);
        $resolver->registerIndex('books', FakeBookModel::class);

        $resolve = $resolver->createResolver();
        $model1 = $resolve('books', '1');
        $model2 = $resolve('books', '2');

        $this->assertInstanceOf(FakeBookModel::class, $model1);
        $this->assertInstanceOf(FakeBookModel::class, $model2);

        $this->assertSame(['_index' => 'books', '_id' => '1', '_score' => 1.5, '_routing' => 'tenant-1'], $model1->scoutMetadata);
        $this->assertSame(['_index' => 'books', '_id' => '2', '_score' => 0.8], $model2->scoutMetadata);
    }
}

final class FakeModelQuery
{
    /** @var list<string> */
    private array $whereInIds = [];

    /**
     * @param class-string<FakeResolverModel> $modelClass
     */
    public function __construct(private readonly string $modelClass) {}

    /**
     * @param array<int, string> $ids
     */
    public function whereIn(string $field, array $ids): self
    {
        $normalizedIds = array_values(array_map('strval', $ids));
        $this->whereInIds = $normalizedIds;

        $modelClass = $this->modelClass;
        $modelClass::$whereInCalls[] = [
            'field' => $field,
            'ids' => $normalizedIds,
        ];

        return $this;
    }

    /**
     * @param array<int, string> $relations
     */
    public function with(array $relations): self
    {
        $modelClass = $this->modelClass;
        $modelClass::$withCalls[] = array_values($relations);

        return $this;
    }

    public function get(): EloquentCollection
    {
        $modelClass = $this->modelClass;
        $modelClass::$getCalls++;

        $idsLookup = array_flip($this->whereInIds);
        $models = array_values(array_filter(
            $modelClass::$records,
            static fn(Model $model): bool => isset($idsLookup[(string) $model->getScoutKey()]),
        ));

        return new EloquentCollection($models);
    }
}

abstract class FakeResolverModel extends Model
{
    /** @var array<int, self> */
    public static array $records = [];

    public static int $newQueryCalls = 0;
    public static int $withTrashedCalls = 0;
    public static int $getCalls = 0;

    /** @var array<int, array{field: string, ids: array<int, string>}> */
    public static array $whereInCalls = [];

    /** @var array<int, array<int, string>> */
    public static array $withCalls = [];

    /** @var array<string, mixed> */
    public array $scoutMetadata = [];

    protected $guarded = [];
    public $timestamps = false;

    public static function resetFakeState(): void
    {
        static::$records = [];
        static::$newQueryCalls = 0;
        static::$withTrashedCalls = 0;
        static::$getCalls = 0;
        static::$whereInCalls = [];
        static::$withCalls = [];
    }

    public function withScoutMetadata(string $key, mixed $value): self
    {
        $this->scoutMetadata[$key] = $value;

        return $this;
    }

    /**
     * @param array<int, string> $ids
     */
    public static function seedRecords(array $ids): void
    {
        static::$records = array_map(static function (string $id) {
            $model = new static();
            $model->id = $id;

            return $model;
        }, $ids);
    }

    public function searchableAs(): string
    {
        return 'fake-index';
    }

    public function getScoutKeyName(): string
    {
        return 'id';
    }

    public function getScoutKey(): string
    {
        return (string) $this->id;
    }

    public function newQuery(): FakeModelQuery
    {
        static::$newQueryCalls++;

        return $this->newFakeQuery();
    }

    public function withTrashed(): FakeModelQuery
    {
        static::$withTrashedCalls++;

        return $this->newFakeQuery();
    }

    private function newFakeQuery(): FakeModelQuery
    {
        /** @var class-string<FakeResolverModel> $class */
        $class = static::class;

        return new FakeModelQuery($class);
    }
}

final class FakeBookModel extends FakeResolverModel
{
    /** @var array<int, self> */
    public static array $records = [];

    public static int $newQueryCalls = 0;
    public static int $withTrashedCalls = 0;
    public static int $getCalls = 0;

    /** @var array<int, array{field: string, ids: array<int, string>}> */
    public static array $whereInCalls = [];

    /** @var array<int, array<int, string>> */
    public static array $withCalls = [];
}

final class FakeAuthorModel extends FakeResolverModel
{
    /** @var array<int, self> */
    public static array $records = [];

    public static int $newQueryCalls = 0;
    public static int $withTrashedCalls = 0;
    public static int $getCalls = 0;

    /** @var array<int, array{field: string, ids: array<int, string>}> */
    public static array $whereInCalls = [];

    /** @var array<int, array<int, string>> */
    public static array $withCalls = [];
}

final class FakeSoftDeleteBookModel extends FakeResolverModel
{
    use SoftDeletes;

    /** @var array<int, self> */
    public static array $records = [];

    public static int $newQueryCalls = 0;
    public static int $withTrashedCalls = 0;
    public static int $getCalls = 0;

    /** @var array<int, array{field: string, ids: array<int, string>}> */
    public static array $whereInCalls = [];

    /** @var array<int, array<int, string>> */
    public static array $withCalls = [];
}
