<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\Engine;
use Laravel\Scout\Builder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    #[Test]
    public function it_is_subclass_of_scout_engine(): void
    {
        $this->assertInstanceOf(\Laravel\Scout\Engines\Engine::class, $this->createEngineWithMockTransport());
    }

    #[Test]
    public function map_ids_returns_empty_collection_when_no_hits(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [];
        $ids = $engine->mapIds($results);

        $this->assertCount(0, $ids);
    }

    #[Test]
    public function map_ids_returns_collection_of_ids(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1'],
                    ['_id' => '2'],
                    ['_id' => '3'],
                ],
            ],
        ];
        $ids = $engine->mapIds($results);

        $this->assertCount(3, $ids);
        $this->assertSame(['1', '2', '3'], $ids->all());
    }

    #[Test]
    public function get_total_count_returns_zero_when_no_hits(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [];
        $count = $engine->getTotalCount($results);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function get_total_count_returns_total_value(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'total' => ['value' => 42],
            ],
        ];
        $count = $engine->getTotalCount($results);

        $this->assertSame(42, $count);
    }

    #[Test]
    public function search_uses_custom_index_from_within(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->within('custom_books');
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame('custom_books', $params['index']);
    }

    #[Test]
    public function search_supports_where_in_and_where_not_in_filters(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->whereIn('status', ['active', 'archived']);
        $builder->whereNotIn('type', ['draft']);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);
        $filters = $params['body']['query']['bool']['filter'];

        $this->assertContains(['terms' => ['status' => ['active', 'archived']]], $filters);
        $this->assertContains(
            ['bool' => ['must_not' => [['terms' => ['type' => ['draft']]]]]],
            $filters,
        );
    }

    #[Test]
    public function search_merges_scout_options_into_params(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->options([
            'routing' => 'tenant-1',
            'body' => [
                'track_total_hits' => true,
            ],
        ]);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame('tenant-1', $params['routing']);
        $this->assertTrue($params['body']['track_total_hits']);
    }

    #[Test]
    public function search_replaces_query_when_scout_options_provide_body_query(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->options([
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['status' => ['value' => 'active']]],
                        ],
                    ],
                ],
            ],
        ]);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame([
            'bool' => [
                'must' => [
                    ['term' => ['status' => ['value' => 'active']]],
                ],
            ],
        ], $params['body']['query']);
    }

    #[Test]
    public function merge_request_body_recursively_merges_associative_arrays(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $base = [
            'runtime_mappings' => [
                'price_with_tax' => [
                    'type' => 'double',
                    'script' => [
                        'source' => "emit(doc['price'].value)",
                    ],
                ],
            ],
            'track_total_hits' => false,
        ];
        $override = [
            'runtime_mappings' => [
                'price_with_tax' => [
                    'script' => [
                        'source' => "emit(doc['price'].value * 1.2)",
                    ],
                ],
                'discounted_price' => [
                    'type' => 'double',
                ],
            ],
            'track_total_hits' => true,
        ];

        $merged = $this->invokeMergeRequestBody($engine, $base, $override);

        $this->assertSame('double', $merged['runtime_mappings']['price_with_tax']['type']);
        $this->assertSame(
            "emit(doc['price'].value * 1.2)",
            $merged['runtime_mappings']['price_with_tax']['script']['source'],
        );
        $this->assertSame(
            ['type' => 'double'],
            $merged['runtime_mappings']['discounted_price'],
        );
        $this->assertTrue($merged['track_total_hits']);
    }

    #[Test]
    public function merge_request_body_replaces_list_sections_instead_of_merging_them(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $base = [
            'sort' => [
                ['created_at' => 'desc'],
            ],
            'highlight' => [
                'pre_tags' => ['<em>'],
                'post_tags' => ['</em>'],
            ],
        ];
        $override = [
            'sort' => [
                ['price' => 'asc'],
            ],
            'highlight' => [
                'pre_tags' => ['<strong>'],
            ],
        ];

        $merged = $this->invokeMergeRequestBody($engine, $base, $override);

        $this->assertSame([['price' => 'asc']], $merged['sort']);
        $this->assertSame(['<strong>'], $merged['highlight']['pre_tags']);
        $this->assertSame(['</em>'], $merged['highlight']['post_tags']);
    }

    #[Test]
    public function merge_request_body_replaces_query_section_entirely(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $base = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['match_all' => new \stdClass()],
                    ],
                ],
            ],
            'size' => 25,
        ];
        $override = [
            'query' => [
                'term' => [
                    'status' => ['value' => 'active'],
                ],
            ],
        ];

        $merged = $this->invokeMergeRequestBody($engine, $base, $override);

        $this->assertSame($override['query'], $merged['query']);
        $this->assertSame(25, $merged['size']);
    }

    #[Test]
    public function search_throws_if_scout_body_option_is_not_array(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->options([
            'body' => 'invalid',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scout options [body] must be an array.');

        $engine->search($builder);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function invokeMergeRequestBody(Engine $engine, array $base, array $override): array
    {
        $method = new \ReflectionMethod(Engine::class, 'mergeRequestBody');

        /** @var array<string, mixed> */
        return $method->invoke($engine, $base, $override);
    }

    private function createScoutBuilder(string $query): Builder
    {
        $model = new class extends Model {
            public function searchableAs(): string
            {
                return 'books';
            }
        };

        return new Builder($model, $query);
    }

    #[Test]
    public function extract_ids_returns_null_for_missing_hits(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $this->assertNull($this->invokeExtractIdsFromResults($engine, []));
        $this->assertNull($this->invokeExtractIdsFromResults($engine, ['hits' => []]));
    }

    #[Test]
    public function extract_ids_returns_null_for_empty_hits_array(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $result = $this->invokeExtractIdsFromResults($engine, ['hits' => ['hits' => []]]);

        $this->assertNull($result);
    }

    #[Test]
    public function extract_ids_returns_ids_and_positions_map(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => 'a'],
                    ['_id' => 'b'],
                    ['_id' => 'c'],
                ],
            ],
        ];

        $result = $this->invokeExtractIdsFromResults($engine, $results);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('ids', $result);
        $this->assertArrayHasKey('positions', $result);
        $this->assertSame(['a', 'b', 'c'], $result['ids']);
        $this->assertSame(['a' => 0, 'b' => 1, 'c' => 2], $result['positions']);
    }

    #[Test]
    public function extract_metadata_returns_empty_for_no_hits(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $this->assertSame([], $this->invokeExtractHitMetadataMap($engine, []));
        $this->assertSame([], $this->invokeExtractHitMetadataMap($engine, ['hits' => []]));
        $this->assertSame([], $this->invokeExtractHitMetadataMap($engine, ['hits' => ['hits' => []]]));
    }

    #[Test]
    public function extract_metadata_extracts_underscore_fields(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books', '_score' => 1.5, '_routing' => 'tenant-1'],
                    ['_id' => '2', '_index' => 'books', '_score' => 0.8],
                ],
            ],
        ];

        $result = $this->invokeExtractHitMetadataMap($engine, $results);

        $this->assertSame([
            '1' => ['_id' => '1', '_index' => 'books', '_score' => 1.5, '_routing' => 'tenant-1'],
            '2' => ['_id' => '2', '_index' => 'books', '_score' => 0.8],
        ], $result);
    }

    #[Test]
    public function extract_metadata_excludes_source(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'hits' => [
                    ['_id' => '1', '_index' => 'books', '_source' => ['title' => 'Test']],
                ],
            ],
        ];

        $result = $this->invokeExtractHitMetadataMap($engine, $results);

        $this->assertArrayNotHasKey('_source', $result['1']);
        $this->assertSame(['_id' => '1', '_index' => 'books'], $result['1']);
    }

    #[Test]
    public function extract_metadata_skips_hits_without_id(): void
    {
        $engine = $this->createEngineWithMockTransport();

        $results = [
            'hits' => [
                'hits' => [
                    ['_index' => 'books', '_score' => 1.0],
                    ['_id' => '', '_index' => 'books'],
                    ['_id' => '1', '_index' => 'books'],
                ],
            ],
        ];

        $result = $this->invokeExtractHitMetadataMap($engine, $results);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('1', $result);
    }

    #[Test]
    public function build_filters_skips_empty_where_ins(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->whereIn('status', []);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertArrayNotHasKey('filter', $params['body']['query']['bool'] ?? []);
    }

    #[Test]
    public function build_filters_skips_empty_where_not_ins(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->whereNotIn('type', []);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertArrayNotHasKey('filter', $params['body']['query']['bool'] ?? []);
    }

    /**
     * @return array{ids: list<string>, positions: array<string, int>}|null
     */
    private function invokeExtractIdsFromResults(Engine $engine, array $results): ?array
    {
        $method = new \ReflectionMethod(Engine::class, 'extractIdsFromResults');

        /** @var array{ids: list<string>, positions: array<string, int>}|null */
        return $method->invoke($engine, $results);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function invokeExtractHitMetadataMap(Engine $engine, array $results): array
    {
        $method = new \ReflectionMethod(Engine::class, 'extractHitMetadataMap');

        /** @var array<string, array<string, mixed>> */
        return $method->invoke($engine, $results);
    }

    private function createEngineWithMockTransport(): Engine
    {
        $client = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts(['http://localhost:9200'])
            ->build();

        return new Engine($client);
    }
}
