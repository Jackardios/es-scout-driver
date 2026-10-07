<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Closure;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Jackardios\EsScoutDriver\Engine\EngineInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class SearchBuilderWriteByQueryTest extends TestCase
{
    /** State without a setter: it is not part of what a search or a write asks for. */
    private const INTERNAL = ['engine', 'aliasRegistry', 'joinedConnectionName', 'baseModelClass'];

    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $container = new Container();
        $container->instance('config', new ConfigRepository(['scout' => ['soft_delete' => true]]));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    /**
     * What a write by query does with each piece of builder state, and a call that gives it a value other than its
     * default (an effective one for a refused property): "sent" changes the request, "refused" throws, "dropped"
     * leaves the request as it was. A new property has to be added here, and its label is checked against the request.
     *
     * @return array<string, array{string, Closure(SearchBuilder): mixed}>
     */
    public static function state(): array
    {
        $set = static fn(string $property, mixed $value): Closure => static fn(SearchBuilder $b) => (
            new ReflectionProperty(SearchBuilder::class, $property)
        )->setValue($b, $value);

        return [
            'indexNames' => ['sent', $set('indexNames', ['TestModel' => 'other_index'])],
            'query' => ['sent', fn(SearchBuilder $b) => $b->query(['term' => ['author_id' => 2]])],
            'boolQuery' => ['sent', fn(SearchBuilder $b) => $b->filter(['term' => ['status' => 'draft']])],
            'softDeleteMode' => ['sent', fn(SearchBuilder $b) => $b->withTrashed()],
            'routing' => ['sent', fn(SearchBuilder $b) => $b->routing('tenant-1')],
            'postFilter' => ['refused', fn(SearchBuilder $b) => $b->postFilter(['term' => ['status' => 'draft']])],
            'minScore' => ['refused', fn(SearchBuilder $b) => $b->minScore(0.5)],
            'knn' => ['refused', fn(SearchBuilder $b) => $b->knn('embedding', [0.1, 0.2], 5)],
            'runtimeMappings' => ['refused', fn(SearchBuilder $b) => $b->runtimeMappings(['flag' => ['type' => 'boolean']])],
            'terminateAfter' => ['refused', fn(SearchBuilder $b) => $b->terminateAfter(10)],
            'collapse' => ['refused', fn(SearchBuilder $b) => $b->collapse('author_id')],
            'size' => ['refused', fn(SearchBuilder $b) => $b->size(5)],
            'from' => ['refused', fn(SearchBuilder $b) => $b->from(10)],
            'searchAfter' => ['refused', fn(SearchBuilder $b) => $b->searchAfter([42])],
            'pointInTime' => ['refused', fn(SearchBuilder $b) => $b->pointInTime('pit-1')],
            'preference' => ['refused', fn(SearchBuilder $b) => $b->preference('_shards:0')],
            'highlight' => ['dropped', fn(SearchBuilder $b) => $b->highlight('title')],
            'sort' => ['dropped', fn(SearchBuilder $b) => $b->sort('price')],
            'rescore' => ['dropped', fn(SearchBuilder $b) => $b->rescore(['match_all' => new \stdClass()])],
            'suggest' => ['dropped', fn(SearchBuilder $b) => $b->suggest('s', ['text' => 'x', 'term' => ['field' => 'title']])],
            'source' => ['dropped', fn(SearchBuilder $b) => $b->source(['title'])],
            'aggregations' => ['dropped', fn(SearchBuilder $b) => $b->aggregate('avg_price', ['avg' => ['field' => 'price']])],
            'trackTotalHits' => ['dropped', fn(SearchBuilder $b) => $b->trackTotalHits(true)],
            'trackScores' => ['dropped', fn(SearchBuilder $b) => $b->trackScores(true)],
            'indicesBoost' => ['dropped', $set('indicesBoost', [['test_index' => 2.0]])],
            'searchType' => ['dropped', fn(SearchBuilder $b) => $b->searchType('dfs_query_then_fetch')],
            'explain' => ['dropped', fn(SearchBuilder $b) => $b->explain(true)],
            'requestCache' => ['dropped', fn(SearchBuilder $b) => $b->requestCache(true)],
            'scriptFields' => ['dropped', fn(SearchBuilder $b) => $b->scriptFields(['x' => ['script' => '1']])],
            'timeout' => ['dropped', fn(SearchBuilder $b) => $b->timeout('5s')],
            'storedFields' => ['dropped', fn(SearchBuilder $b) => $b->storedFields(['title'])],
            'docvalueFields' => ['dropped', fn(SearchBuilder $b) => $b->docvalueFields(['price'])],
            'version' => ['dropped', fn(SearchBuilder $b) => $b->version()],
            'queryModifiers' => ['dropped', fn(SearchBuilder $b) => $b->modifyQuery(static fn() => null)],
            'modelModifiers' => ['dropped', fn(SearchBuilder $b) => $b->modifyModels(static fn($models) => $models)],
            'relations' => ['dropped', fn(SearchBuilder $b) => $b->with(['author'])],
        ];
    }

    #[Test]
    public function every_piece_of_builder_state_is_classified(): void
    {
        $properties = array_map(
            static fn(ReflectionProperty $property): string => $property->getName(),
            array_filter(
                (new ReflectionClass(SearchBuilder::class))->getProperties(),
                static fn(ReflectionProperty $property): bool => !$property->isStatic()
                    && $property->getDeclaringClass()->getName() === SearchBuilder::class,
            ),
        );
        sort($properties);

        $classified = [...self::INTERNAL, ...array_keys(self::state())];
        sort($classified);

        $this->assertSame($properties, $classified);
    }

    #[Test]
    #[DataProvider('state')]
    public function the_label_of_a_property_is_what_a_write_by_query_does_with_it(string $label, Closure $setter): void
    {
        $property = (string) $this->dataName();

        foreach (['deleteByQuery', 'updateByQuery'] as $method) {
            $write = fn(?Closure $configure): array => $this->write($method, $configure);
            $untouched = $write(null);

            if ($label === 'refused') {
                try {
                    $write($setter);
                    $this->fail("$method() did not refuse $property.");
                } catch (LogicException $e) {
                    $this->assertStringContainsString("$method() cannot be combined with $property()", $e->getMessage());
                }

                continue;
            }

            $request = $write($setter);

            $label === 'sent'
                ? $this->assertNotEquals($untouched, $request, "$property does not reach the $method() request.")
                : $this->assertEquals($untouched, $request, "$property changes the $method() request.");
        }
    }

    /** @return iterable<string, array{Closure(SearchBuilder): mixed}> */
    public static function valuesThatRestrictNothing(): iterable
    {
        yield 'minScore(0.0)' => [fn(SearchBuilder $b) => $b->minScore(0.0)];
        yield 'minScore(-1.0)' => [fn(SearchBuilder $b) => $b->minScore(-1.0)];
        yield 'runtimeMappings([])' => [fn(SearchBuilder $b) => $b->runtimeMappings([])];
        yield 'terminateAfter(0)' => [fn(SearchBuilder $b) => $b->terminateAfter(0)];
        yield 'from(0)' => [fn(SearchBuilder $b) => $b->from(0)];
        yield 'searchAfter([])' => [fn(SearchBuilder $b) => $b->searchAfter([])];
        yield 'preference(_local)' => [fn(SearchBuilder $b) => $b->preference('_local')];
        yield 'preference(_prefer_nodes)' => [fn(SearchBuilder $b) => $b->preference('_prefer_nodes:a,b')];
        yield 'preference(session)' => [fn(SearchBuilder $b) => $b->preference('user-42')];
        yield 'knnRaw([])' => [fn(SearchBuilder $b) => $b->knnRaw([])];
        yield 'collapseRaw([])' => [fn(SearchBuilder $b) => $b->collapseRaw([])];
    }

    #[Test]
    #[DataProvider('valuesThatRestrictNothing')]
    public function a_value_that_restricts_nothing_is_not_refused(Closure $configure): void
    {
        foreach (['deleteByQuery', 'updateByQuery'] as $method) {
            $this->assertEquals($this->write($method, null), $this->write($method, $configure));
        }
    }

    /** @return iterable<string, array{Closure(SearchBuilder): mixed, string}> */
    public static function smallestEffectiveValues(): iterable
    {
        yield 'minScore just above 0' => [fn(SearchBuilder $b) => $b->minScore(0.001), 'minScore()'];
        yield 'terminateAfter(1)' => [fn(SearchBuilder $b) => $b->terminateAfter(1), 'terminateAfter()'];
        yield 'from(1)' => [fn(SearchBuilder $b) => $b->from(1), 'from()'];
        yield 'size(0)' => [fn(SearchBuilder $b) => $b->size(0), 'size()'];
        yield 'searchAfter of one value' => [fn(SearchBuilder $b) => $b->searchAfter([0]), 'searchAfter()'];
        yield 'preference(_only_local)' => [fn(SearchBuilder $b) => $b->preference('_only_local'), 'preference()'];
        yield 'preference(_only_nodes)' => [fn(SearchBuilder $b) => $b->preference('_only_nodes:a'), 'preference()'];
        yield 'preference(_shards with a copy)' => [
            fn(SearchBuilder $b) => $b->preference('_shards:2,3|_local'),
            'preference()',
        ];
    }

    #[Test]
    #[DataProvider('smallestEffectiveValues')]
    public function the_smallest_value_that_restricts_is_refused(Closure $configure, string $name): void
    {
        foreach (['deleteByQuery', 'updateByQuery'] as $method) {
            try {
                $this->write($method, $configure);
                $this->fail("$method() did not refuse $name.");
            } catch (LogicException $e) {
                $this->assertStringContainsString("$method() cannot be combined with $name", $e->getMessage());
            }
        }
    }

    #[Test]
    public function a_write_by_query_names_every_dropped_restriction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('deleteByQuery() cannot be combined with minScore(), size(): it sends only');

        $this->write('deleteByQuery', fn(SearchBuilder $b) => $b->minScore(0.5)->size(5));
    }

    #[Test]
    public function the_scalar_clear_methods_reset_their_option(): void
    {
        $builder = $this->createBuilder($this->createStub(EngineInterface::class))
            ->from(10)->clearFrom()
            ->size(5)->clearSize()
            ->minScore(0.5)->clearMinScore()
            ->terminateAfter(10)->clearTerminateAfter()
            ->runtimeMappings(['flag' => ['type' => 'boolean']])->clearRuntimeMappings()
            ->preference('_local')->clearPreference();

        $this->assertNull($builder->getFrom());
        $this->assertNull($builder->getSize());
        $this->assertNull($builder->getMinScore());
        $this->assertNull($builder->getPreference());
        $this->assertEquals(
            $this->createBuilder($this->createStub(EngineInterface::class))->buildParams(),
            $builder->buildParams(),
        );
    }

    /**
     * The request of a write by query of a builder with a query, after $configure.
     *
     * @return array<string, mixed>
     */
    private function write(string $method, ?Closure $configure): array
    {
        $request = [];
        $capture = function (array $params) use (&$request): array {
            $request = $params;

            return [];
        };

        $engine = $this->createStub(EngineInterface::class);
        $engine->method('deleteByQueryRaw')->willReturnCallback($capture);
        $engine->method('updateByQueryRaw')->willReturnCallback($capture);

        $builder = $this->createBuilder($engine)->query(['term' => ['author_id' => 1]]);

        if ($configure !== null) {
            $configure($builder);
        }

        $method === 'deleteByQuery' ? $builder->deleteByQuery() : $builder->updateByQuery(['source' => 'ctx.op = "noop"']);

        $this->assertNotSame([], $request);

        return $request;
    }

    private function createBuilder(EngineInterface $engine): SearchBuilder
    {
        $builder = (new ReflectionClass(SearchBuilder::class))->newInstanceWithoutConstructor();

        (new ReflectionProperty(SearchBuilder::class, 'engine'))->setValue($builder, $engine);
        (new ReflectionProperty(SearchBuilder::class, 'indexNames'))->setValue($builder, ['TestModel' => 'test_index']);

        return $builder;
    }
}
