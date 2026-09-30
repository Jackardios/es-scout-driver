<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Elastic\Elasticsearch\Client;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Jackardios\EsScoutDriver\Engine\Engine;
use Laravel\Scout\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EngineTest extends TestCase
{
    private Container $previousContainer;
    private ConfigRepository $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $this->config = new ConfigRepository();
        $container->instance('config', $this->config);
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

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
    public function create_index_does_not_send_the_scout_primary_key_option(): void
    {
        $http = new FakeHttpClient([[200, ['acknowledged' => true]], [200, ['acknowledged' => true]]]);
        $engine = new Engine($http->client());

        $engine->createIndex('books', ['primaryKey' => 'id']);
        $engine->createIndex('authors', ['primaryKey' => 'id', 'mappings' => ['properties' => ['name' => ['type' => 'text']]]]);

        $this->assertSame('/books', $http->requests[0]->getUri()->getPath());
        $this->assertSame('', (string) $http->requests[0]->getBody());
        $this->assertSame(
            ['mappings' => ['properties' => ['name' => ['type' => 'text']]]],
            json_decode((string) $http->requests[1]->getBody(), true),
        );
    }

    #[Test]
    public function connection_switches_to_a_configured_client(): void
    {
        $analytics = (new FakeHttpClient())->client();
        Container::getInstance()->instance('elastic.client.connection.analytics', $analytics);

        $this->assertSame($analytics, $this->createEngineWithMockTransport()->connection('analytics')->getClient());
    }

    #[Test]
    public function connection_refuses_an_unknown_connection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Elasticsearch connection [missing] is not configured in elastic.client.connections.');

        $this->createEngineWithMockTransport()->connection('missing');
    }

    #[Test]
    public function search_uses_the_configured_scout_query_type(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('dune');
        $builder->callback = static fn($client, $query, $params) => $params;

        $this->assertSame(['simple_query_string' => ['query' => 'dune']], $engine->search($builder)['body']['query']);

        $this->config->set('elastic.scout.scout_query_type', ' Query_String ');

        $this->assertSame(['query_string' => ['query' => 'dune']], $engine->search($builder)['body']['query']);
    }

    #[Test]
    public function search_refuses_an_unknown_scout_query_type(): void
    {
        $this->config->set('elastic.scout.scout_query_type', 'match');
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('dune');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Config [elastic.scout.scout_query_type] must be one of [simple_query_string, query_string], got [match].',
        );

        $engine->search($builder);
    }

    #[Test]
    public function search_accepts_a_callback_returning_the_client_response(): void
    {
        $result = ['hits' => ['total' => ['value' => 1], 'hits' => [['_id' => '1']]]];
        $http = new FakeHttpClient([[200, $result]]);
        $engine = new Engine($http->client());
        $builder = $this->createScoutBuilder('test');
        $builder->callback = static fn(Client $client, ?string $query, array $params) => $client->search($params);

        $this->assertSame($result, $engine->search($builder));
    }

    #[Test]
    public function paginate_sends_the_offset_of_the_page(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->paginate($builder, 15, 3);

        $this->assertSame(30, $params['body']['from']);
        $this->assertSame(15, $params['body']['size']);
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function invalidPages(): iterable
    {
        yield 'perPage below 1' => [0, 1, 'perPage must be greater than 0.'];
        yield 'page below 1' => [15, -1, 'page must be greater than or equal to 1.'];
        yield 'offset overflow' => [15, PHP_INT_MAX, 'page is too large: the offset of its last hit does not fit in an integer.'];
    }

    #[Test]
    #[DataProvider('invalidPages')]
    public function paginate_refuses_an_invalid_page(int $perPage, int $page, string $message): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->callback = static fn($client, $query, $params) => $params;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $engine->paginate($builder, $perPage, $page);
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
    public function search_supports_where_filters(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->where('status', 'active');
        $builder->where('deleted_by', null);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame([
            ['term' => ['status' => ['value' => 'active']]],
            ['bool' => ['must_not' => [['exists' => ['field' => 'deleted_by']]]]],
        ], $params['body']['query']['bool']['filter']);
    }

    #[Test]
    public function search_filters_soft_deleted_models_and_keeps_documents_without_the_flag(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $search = static function (callable $configure) use ($engine): array {
            $builder = new Builder(new class extends Model {
                public function searchableAs(): string
                {
                    return 'books';
                }
            }, 'test', null, true);
            $configure($builder);
            $builder->callback = static fn($client, $query, $params) => $params;

            return $engine->search($builder)['body']['query']['bool']['filter'] ?? [];
        };

        $this->assertSame([[
            'bool' => [
                'should' => [
                    ['term' => ['__soft_deleted' => ['value' => 0]]],
                    ['bool' => ['must_not' => [['exists' => ['field' => '__soft_deleted']]]]],
                ],
                'minimum_should_match' => 1,
            ],
        ]], $search(static fn() => null));
        $this->assertSame(
            [['term' => ['__soft_deleted' => ['value' => 1]]]],
            $search(static fn(Builder $builder) => $builder->onlyTrashed()),
        );
        $this->assertSame([], $search(static fn(Builder $builder) => $builder->withTrashed()));
    }

    #[Test]
    public function search_supports_where_comparison_operators(): void
    {
        $this->skipUnlessScoutSupportsWhereOperators();

        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->where('price', '>', 10);
        $builder->where('price', '<=', 100);
        $builder->where('status', '!=', 'draft');
        $builder->where('published_at', '<>', null);
        $builder->where('created_at', '>=', new \DateTimeImmutable('2024-01-31 10:00:00', new \DateTimeZone('+03:00')));
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame([
            ['range' => ['price' => ['gt' => 10]]],
            ['range' => ['price' => ['lte' => 100]]],
            ['bool' => ['must_not' => [['term' => ['status' => ['value' => 'draft']]]]]],
            ['exists' => ['field' => 'published_at']],
            ['range' => ['created_at' => ['gte' => '2024-01-31T10:00:00+03:00']]],
        ], $params['body']['query']['bool']['filter']);
    }

    #[Test]
    public function search_rejects_unsupported_where_operators(): void
    {
        $this->skipUnlessScoutSupportsWhereOperators();

        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->where('title', 'like', '%book%');
        $builder->callback = static fn($client, $query, $params) => $params;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported where operator [like] for field [title].');

        $engine->search($builder);
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

    private function skipUnlessScoutSupportsWhereOperators(): void
    {
        if ((new \ReflectionMethod(Builder::class, 'where'))->getNumberOfParameters() < 3) {
            $this->markTestSkipped('Scout 10 where() takes no operator.');
        }
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
    public function build_filters_match_nothing_for_empty_where_ins(): void
    {
        $engine = $this->createEngineWithMockTransport();
        $builder = $this->createScoutBuilder('test');
        $builder->whereIn('status', []);
        $builder->callback = static fn($client, $query, $params) => $params;

        $params = $engine->search($builder);

        $this->assertSame([['terms' => ['status' => []]]], $params['body']['query']['bool']['filter']);
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
        return new Engine((new FakeHttpClient())->client());
    }
}
