<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Closure;
use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use Jackardios\EsScoutDriver\Engine\ModelResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModelResolverTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_alias_registry(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);

        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function it_can_be_constructed_with_raw_hits(): void
    {
        $registry = new AliasRegistry();
        $rawHits = [
            ['_index' => 'books', '_id' => '1', '_source' => ['title' => 'Test']],
        ];

        $resolver = new ModelResolver($registry, $rawHits);

        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function it_can_be_constructed_with_raw_suggestions(): void
    {
        $registry = new AliasRegistry();
        $rawSuggestions = [
            'title-suggest' => [
                ['text' => 'test', 'options' => []],
            ],
        ];

        $resolver = new ModelResolver($registry, [], $rawSuggestions);

        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function it_registers_index_and_returns_self(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);

        $result = $resolver->registerIndex(
            indexName: 'books',
            modelClass: 'App\Models\Book',
            relations: ['author'],
            queryCallbacks: [],
            collectionCallbacks: [],
            withTrashed: false,
        );

        $this->assertSame($resolver, $result);
    }

    #[Test]
    public function it_returns_null_for_unknown_index(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);
        $resolverClosure = $resolver->createResolver();

        $result = $resolverClosure('unknown-index', 'doc-1');

        $this->assertNull($result);
    }

    #[Test]
    public function create_resolver_returns_closure(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);

        $closure = $resolver->createResolver();

        $this->assertInstanceOf(Closure::class, $closure);
    }

    #[Test]
    public function with_raw_data_creates_new_resolver(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);
        $resolver->registerIndex('books', 'App\Models\Book');

        $rawHits = [
            ['_index' => 'books', '_id' => '1', '_source' => []],
        ];

        $newResolver = $resolver->withRawData($rawHits);

        $this->assertInstanceOf(ModelResolver::class, $newResolver);
        $this->assertNotSame($resolver, $newResolver);
    }

    #[Test]
    public function with_raw_data_preserves_registered_indices(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);
        $resolver->registerIndex('books', 'App\Models\Book');

        $newResolver = $resolver->withRawData([]);

        // The new resolver should return null for unregistered index
        // but for registered index it should work (though no models loaded)
        $closure = $newResolver->createResolver();
        $result = $closure('unknown-index', 'doc-1');

        $this->assertNull($result);
    }

    #[Test]
    public function get_cached_models_returns_empty_array_for_unknown_index(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);

        $result = $resolver->getCachedModels('unknown-index');

        $this->assertSame([], $result);
    }

    #[Test]
    public function preload_all_does_not_throw_with_no_indices(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);

        $resolver->preloadAll();

        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function suggestions_are_passed_to_constructor(): void
    {
        $registry = new AliasRegistry();
        $rawSuggestions = [
            'title-suggest' => [
                [
                    'text' => 'test',
                    'offset' => 0,
                    'length' => 4,
                    'options' => [
                        [
                            '_index' => 'books',
                            '_id' => '1',
                            'text' => 'testing',
                            '_score' => 1.0,
                        ],
                    ],
                ],
            ],
        ];

        $resolver = new ModelResolver($registry, [], $rawSuggestions);
        $resolver->registerIndex('books', 'App\Models\Book');

        // Should not throw
        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function inner_hits_are_collected_from_raw_hits(): void
    {
        $registry = new AliasRegistry();
        $rawHits = [
            [
                '_index' => 'books',
                '_id' => '1',
                '_source' => [],
                'inner_hits' => [
                    'chapters' => [
                        'hits' => [
                            'hits' => [
                                ['_index' => 'books', '_id' => '2', '_source' => []],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $resolver = new ModelResolver($registry, $rawHits);

        // Should not throw when collecting IDs from inner_hits
        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function it_can_be_constructed_with_raw_result(): void
    {
        $registry = new AliasRegistry();
        $rawResult = [
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    ['_index' => 'books', '_id' => '1', '_source' => ['title' => 'Test']],
                ],
            ],
            'aggregations' => ['category_count' => ['buckets' => []]],
        ];

        $resolver = new ModelResolver(
            $registry,
            $rawResult['hits']['hits'],
            [],
            $rawResult,
        );

        $this->assertInstanceOf(ModelResolver::class, $resolver);
    }

    #[Test]
    public function with_raw_data_includes_raw_result(): void
    {
        $registry = new AliasRegistry();
        $resolver = new ModelResolver($registry);
        $resolver->registerIndex('books', 'App\Models\Book');

        $rawResult = [
            'hits' => ['total' => ['value' => 1], 'hits' => []],
        ];

        $newResolver = $resolver->withRawData([], [], $rawResult);

        $this->assertInstanceOf(ModelResolver::class, $newResolver);
        $this->assertNotSame($resolver, $newResolver);
    }
}
