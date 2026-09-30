<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use Jackardios\EsScoutDriver\Engine\EngineInterface;
use Jackardios\EsScoutDriver\Engine\ModelResolver;
use Jackardios\EsScoutDriver\Enums\SoftDeleteMode;
use Jackardios\EsScoutDriver\Enums\SortOrder;
use Jackardios\EsScoutDriver\Exceptions\AmbiguousJoinedIndexException;
use Jackardios\EsScoutDriver\Exceptions\IncompatibleSearchConnectionException;
use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Exceptions\ModelNotJoinedException;
use Jackardios\EsScoutDriver\Exceptions\NotSearchableModelException;
use Jackardios\EsScoutDriver\Aggregations\AggregationInterface;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\Sort\FieldSort;
use Jackardios\EsScoutDriver\Sort\SortInterface;
use Jackardios\EsScoutDriver\Support\ConfigOption;
use InvalidArgumentException;
use LogicException;
use stdClass;
use Throwable;

class SearchBuilder
{
    use Conditionable;
    use Macroable;
    use Tappable;

    public const DEFAULT_PAGE_SIZE = 10;

    private EngineInterface $engine;
    private AliasRegistry $aliasRegistry;
    private ?string $joinedConnectionName = null;
    private ?string $baseModelClass = null;

    /** @var array<class-string<Model>, string> model class => index name */
    private array $indexNames = [];

    /** @var array<string, mixed>|null */
    private ?array $query = null;
    private ?BoolQuery $boolQuery = null;
    private SoftDeleteMode $softDeleteMode = SoftDeleteMode::ExcludeTrashed;
    /** @var array<string, mixed> */
    private array $highlight = [];
    /** @var array<int, array<string, mixed>> */
    private array $sort = [];
    /** @var array<string, mixed> */
    private array $rescore = [];
    private ?int $from = null;
    private ?int $size = null;
    /** @var array<string, mixed> */
    private array $suggest = [];
    /** @var bool|string|array<string, mixed>|null */
    private bool|string|array|null $source = null;
    /** @var array<string, mixed> */
    private array $collapse = [];
    /** @var array<int|string, AggregationInterface|array<string, mixed>> Integer-like names become integer keys */
    private array $aggregations = [];
    private ?array $postFilter = null;
    private int|bool|null $trackTotalHits = null;
    private ?bool $trackScores = null;
    private ?float $minScore = null;
    /** @var list<array<string, float>> */
    private array $indicesBoost = [];
    private ?string $searchType = null;
    private ?string $preference = null;
    private ?array $pointInTime = null;
    private ?array $searchAfter = null;
    private ?array $routing = null;
    private ?bool $explain = null;
    private ?int $terminateAfter = null;
    private ?bool $requestCache = null;
    private ?array $scriptFields = null;
    private ?array $runtimeMappings = null;
    private ?string $timeout = null;
    private ?array $storedFields = null;
    private ?array $docvalueFields = null;
    private ?bool $version = null;
    private ?array $knn = null;

    /** @var array<string, array<int, Closure>> index => callbacks */
    private array $queryModifiers = [];

    /** @var array<string, array<int, Closure>> index => callbacks */
    private array $modelModifiers = [];

    /** @var array<string, array<int|string, string|Closure>> index => relations */
    private array $relations = [];

    public function __construct(Model $model, QueryInterface|Closure|array|null $query = null)
    {
        $this->engine = $model->searchableUsing();
        $this->aliasRegistry = new AliasRegistry($this->engine->getClient());

        $this->join(get_class($model));

        if ($query !== null) {
            $this->query($query);
        }
    }

    // ---- Query methods ----

    public function query(QueryInterface|Closure|array $query): static
    {
        $this->query = $this->resolveQueryToArray($query, 'Search query');
        return $this;
    }

    public function clearQuery(): static
    {
        $this->query = null;
        return $this;
    }

    // ---- Bool query shortcuts (variadic add methods) ----

    /** @param QueryInterface|Closure|array ...$queries */
    public function must(QueryInterface|Closure|array ...$queries): static
    {
        $this->refuseEmptyClauses('must', $queries);
        $this->boolQuery()->must(...$queries);
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function mustNot(QueryInterface|Closure|array ...$queries): static
    {
        $this->refuseEmptyClauses('mustNot', $queries);
        $this->boolQuery()->mustNot(...$queries);
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function should(QueryInterface|Closure|array ...$queries): static
    {
        $this->refuseEmptyClauses('should', $queries);
        $this->boolQuery()->should(...$queries);
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function filter(QueryInterface|Closure|array ...$queries): static
    {
        $this->refuseEmptyClauses('filter', $queries);
        $this->boolQuery()->filter(...$queries);
        return $this;
    }

    public function boolQuery(): BoolQuery
    {
        if ($this->boolQuery === null) {
            $this->boolQuery = new BoolQuery();
        }

        return $this->boolQuery;
    }

    public function hasBoolQuery(): bool
    {
        return $this->boolQuery !== null;
    }

    public function getBoolQuery(): ?BoolQuery
    {
        return $this->boolQuery;
    }

    public function clearBoolQuery(): static
    {
        $this->boolQuery = null;
        return $this;
    }

    // ---- Soft delete methods ----

    public function softDelete(SoftDeleteMode $mode): static
    {
        $this->softDeleteMode = $mode;
        return $this;
    }

    public function withTrashed(): static
    {
        $this->softDeleteMode = SoftDeleteMode::WithTrashed;
        return $this;
    }

    public function onlyTrashed(): static
    {
        $this->softDeleteMode = SoftDeleteMode::OnlyTrashed;
        return $this;
    }

    public function excludeTrashed(): static
    {
        $this->softDeleteMode = SoftDeleteMode::ExcludeTrashed;
        return $this;
    }

    public function getSoftDeleteMode(): SoftDeleteMode
    {
        return $this->softDeleteMode;
    }

    // ---- Highlight ----

    public function highlightRaw(array $highlight): static
    {
        $this->highlight = $highlight;
        return $this;
    }

    public function highlight(
        string $field,
        array $parameters = [],
        ?int $fragmentSize = null,
        ?int $numberOfFragments = null,
        ?array $preTags = null,
        ?array $postTags = null,
    ): static {
        if (!isset($this->highlight['fields'])) {
            $this->highlight['fields'] = [];
        }

        $fieldConfig = $parameters;

        if ($fragmentSize !== null) {
            $fieldConfig['fragment_size'] = $fragmentSize;
        }
        if ($numberOfFragments !== null) {
            $fieldConfig['number_of_fragments'] = $numberOfFragments;
        }
        if ($preTags !== null) {
            $fieldConfig['pre_tags'] = $preTags;
        }
        if ($postTags !== null) {
            $fieldConfig['post_tags'] = $postTags;
        }

        $this->highlight['fields'][$field] = $fieldConfig !== [] ? $fieldConfig : new stdClass();
        return $this;
    }

    public function highlightGlobal(
        ?int $fragmentSize = null,
        ?int $numberOfFragments = null,
        ?array $preTags = null,
        ?array $postTags = null,
        ?string $type = null,
        ?string $boundaryScanner = null,
        ?string $encoder = null,
    ): static {
        if ($fragmentSize !== null) {
            $this->highlight['fragment_size'] = $fragmentSize;
        }
        if ($numberOfFragments !== null) {
            $this->highlight['number_of_fragments'] = $numberOfFragments;
        }
        if ($preTags !== null) {
            $this->highlight['pre_tags'] = $preTags;
        }
        if ($postTags !== null) {
            $this->highlight['post_tags'] = $postTags;
        }
        if ($type !== null) {
            $this->highlight['type'] = $type;
        }
        if ($boundaryScanner !== null) {
            $this->highlight['boundary_scanner'] = $boundaryScanner;
        }
        if ($encoder !== null) {
            $this->highlight['encoder'] = $encoder;
        }
        return $this;
    }

    public function clearHighlight(): static
    {
        $this->highlight = [];
        return $this;
    }

    // ---- Sort ----

    public function sortRaw(array $sort): static
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @param SortOrder|string|null $direction asc when null; set it on the sort object for a SortInterface
     */
    public function sort(
        string|SortInterface $field,
        SortOrder|string|null $direction = null,
        string|int|float|bool|null $missing = null,
        ?string $mode = null,
        ?string $unmappedType = null,
    ): static {
        if ($field instanceof SortInterface) {
            if ($direction !== null || $missing !== null || $mode !== null || $unmappedType !== null) {
                throw new InvalidArgumentException(
                    'sort() takes no direction or options with a SortInterface; set them on the sort object.',
                );
            }

            $this->sort[] = $field->toArray();
            return $this;
        }

        $sort = (new FieldSort($field))->order($direction ?? SortOrder::Asc);

        if ($missing !== null) {
            $sort->missing($missing);
        }
        if ($mode !== null) {
            $sort->mode($mode);
        }
        if ($unmappedType !== null) {
            $sort->unmappedType($unmappedType);
        }

        $this->sort[] = $sort->toArray();
        return $this;
    }

    public function clearSort(): static
    {
        $this->sort = [];
        return $this;
    }

    // ---- Rescore ----

    public function rescoreRaw(array $rescore): static
    {
        $this->rescore = $rescore;
        return $this;
    }

    public function rescore(QueryInterface|Closure|array $query, ?int $windowSize = null, ?float $queryWeight = null, ?float $rescoreQueryWeight = null): static
    {
        $rescore = ['query' => ['rescore_query' => $this->resolveQueryToArray($query, 'Rescore query')]];

        if ($queryWeight !== null) {
            $rescore['query']['query_weight'] = $queryWeight;
        }

        if ($rescoreQueryWeight !== null) {
            $rescore['query']['rescore_query_weight'] = $rescoreQueryWeight;
        }

        if ($windowSize !== null) {
            $rescore['window_size'] = $windowSize;
        }

        $this->rescore = $rescore;
        return $this;
    }

    public function clearRescore(): static
    {
        $this->rescore = [];
        return $this;
    }

    // ---- Pagination ----

    public function from(int $from): static
    {
        $this->from = $from;
        return $this;
    }

    public function size(int $size): static
    {
        $this->size = $size;
        return $this;
    }

    // ---- Suggest ----

    public function suggestRaw(array $suggest): static
    {
        $this->suggest = $suggest;
        return $this;
    }

    public function suggest(string $name, array $definition): static
    {
        $this->suggest[$name] = $definition;
        return $this;
    }

    public function clearSuggest(): static
    {
        $this->suggest = [];
        return $this;
    }

    // ---- Source ----

    public function sourceRaw(bool|string|array $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function withoutSource(): static
    {
        $this->source = false;
        return $this;
    }

    public function source(array $includes, ?array $excludes = null): static
    {
        if ($excludes !== null) {
            $this->source = ['includes' => $includes, 'excludes' => $excludes];
        } else {
            $this->source = $includes;
        }

        return $this;
    }

    public function clearSource(): static
    {
        $this->source = null;
        return $this;
    }

    // ---- Collapse ----

    public function collapseRaw(array $collapse): static
    {
        $this->collapse = $collapse;
        return $this;
    }

    public function collapse(string $field): static
    {
        $this->collapse = ['field' => $field];
        return $this;
    }

    public function clearCollapse(): static
    {
        $this->collapse = [];
        return $this;
    }

    // ---- Aggregate ----

    public function aggregateRaw(array $aggregations): static
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    /**
     * @param AggregationInterface|array<string, mixed> $definition serialized when the request is built
     *
     * @throws InvalidArgumentException when an integer name would turn the aggregations into a JSON list
     */
    public function aggregate(string $name, AggregationInterface|array $definition): static
    {
        $aggregations = $this->aggregations;
        $aggregations[$name] = $definition;

        if (array_is_list($aggregations)) {
            throw new InvalidArgumentException(sprintf(
                'Aggregation name [%s] would send the aggregations as a JSON list; use a name that is not an integer.',
                $name,
            ));
        }

        $this->aggregations = $aggregations;
        return $this;
    }

    public function clearAggregations(): static
    {
        $this->aggregations = [];
        return $this;
    }

    // ---- Post Filter ----

    public function postFilter(QueryInterface|Closure|array $query): static
    {
        $this->postFilter = $this->resolveQueryToArray($query, 'Post filter');
        return $this;
    }

    public function clearPostFilter(): static
    {
        $this->postFilter = null;
        return $this;
    }

    // ---- Track & Score ----

    public function trackTotalHits(int|bool $trackTotalHits): static
    {
        $this->trackTotalHits = $trackTotalHits;
        return $this;
    }

    public function trackScores(bool $trackScores): static
    {
        $this->trackScores = $trackScores;
        return $this;
    }

    public function minScore(float $minScore): static
    {
        $this->minScore = $minScore;
        return $this;
    }

    // ---- Search config ----

    public function searchType(string $searchType): static
    {
        $this->searchType = $searchType;
        return $this;
    }

    public function preference(string $preference): static
    {
        $this->preference = $preference;
        return $this;
    }

    public function pointInTime(string $id, ?string $keepAlive = null): static
    {
        $this->pointInTime = ['id' => $id];

        if ($keepAlive !== null) {
            $this->pointInTime['keep_alive'] = $keepAlive;
        }

        return $this;
    }

    public function clearPointInTime(): static
    {
        $this->pointInTime = null;
        return $this;
    }

    public function searchAfter(array $searchAfter): static
    {
        $this->searchAfter = $searchAfter;
        return $this;
    }

    public function clearSearchAfter(): static
    {
        $this->searchAfter = null;
        return $this;
    }

    public function routing(string|int|array|null $routing): static
    {
        $this->routing = match (true) {
            $routing === null => null,
            is_array($routing) => $routing,
            default => [(string) $routing],
        };
        return $this;
    }

    public function clearRouting(): static
    {
        $this->routing = null;
        return $this;
    }

    public function explain(bool $explain): static
    {
        $this->explain = $explain;
        return $this;
    }

    public function terminateAfter(int $terminateAfter): static
    {
        $this->terminateAfter = $terminateAfter;
        return $this;
    }

    public function requestCache(bool $requestCache): static
    {
        $this->requestCache = $requestCache;
        return $this;
    }

    // ---- Search options ----

    public function timeout(string $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    /** @param array<int, string> $fields */
    public function storedFields(array $fields): static
    {
        $this->storedFields = $fields;
        return $this;
    }

    /** @param array<int, string|array<string, mixed>> $fields */
    public function docvalueFields(array $fields): static
    {
        $this->docvalueFields = $fields;
        return $this;
    }

    public function version(bool $version = true): static
    {
        $this->version = $version;
        return $this;
    }

    public function scriptFields(array $scriptFields): static
    {
        $this->scriptFields = $scriptFields;
        return $this;
    }

    public function runtimeMappings(array $runtimeMappings): static
    {
        $this->runtimeMappings = $runtimeMappings;
        return $this;
    }

    // ---- KNN (top-level for ES 8.12+) ----

    /**
     * @param array<int, float> $queryVector
     */
    public function knn(
        string $field,
        array $queryVector,
        int $k,
        ?int $numCandidates = null,
        ?float $similarity = null,
        QueryInterface|array|null $filter = null,
    ): static {
        $knn = new KnnQuery($field, $queryVector, $k);

        if ($numCandidates !== null) {
            $knn->numCandidates($numCandidates);
        }

        if ($similarity !== null) {
            $knn->similarity($similarity);
        }

        if ($filter !== null) {
            $knn->filter($filter);
        }

        $this->knn = $knn->toArray()['knn'];

        return $this;
    }

    public function knnRaw(array $knn): static
    {
        $this->knn = $knn;
        return $this;
    }

    public function clearKnn(): static
    {
        $this->knn = null;
        return $this;
    }

    public function getKnn(): ?array
    {
        return $this->knn;
    }

    // ---- Join & Load ----

    public function join(string $modelClass, ?float $boost = null): static
    {
        if (
            !is_a($modelClass, Model::class, true)
            || !in_array(Searchable::class, class_uses_recursive($modelClass), true)
        ) {
            throw new NotSearchableModelException($modelClass);
        }

        /** @var Model $model */
        $model = new $modelClass();
        $indexName = $model->searchableAs();
        $connectionName = $this->resolveEffectiveConnectionName($model->searchableConnection());

        if ($this->joinedConnectionName === null) {
            $this->joinedConnectionName = $connectionName;
            $this->baseModelClass = $modelClass;
        } elseif ($this->joinedConnectionName !== $connectionName) {
            throw new IncompatibleSearchConnectionException(
                $this->baseModelClass ?? 'unknown',
                $this->joinedConnectionName,
                $modelClass,
                $connectionName,
            );
        }

        $registeredModelClass = array_search($indexName, $this->indexNames, true);
        if (is_string($registeredModelClass) && $registeredModelClass !== $modelClass) {
            throw new AmbiguousJoinedIndexException($indexName, $registeredModelClass, $modelClass);
        }

        $this->indexNames[$modelClass] = $indexName;

        if ($boost !== null) {
            $this->indicesBoost[] = [$indexName => $boost];
        }

        return $this;
    }

    public function clearIndicesBoost(): static
    {
        $this->indicesBoost = [];
        return $this;
    }

    /**
     * Eager load relations on the models.
     *
     * @param array<int|string, string|Closure> $relations Relations to eager load, as for Eloquent's with()
     * @param string|null $modelClass Model class to apply relations to (for multi-index searches)
     */
    public function with(array $relations, ?string $modelClass = null): static
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $merged = $this->relations[$indexName] ?? [];

        foreach ($relations as $key => $relation) {
            if (is_string($key)) {
                $merged[$key] = $relation;
            } elseif (!in_array($relation, $merged, true)) {
                $merged[] = $relation;
            }
        }

        $this->relations[$indexName] = $merged;
        return $this;
    }

    // ---- Eloquent callbacks ----

    /**
     * Add a callback to modify the Eloquent query before loading models.
     *
     * @param Closure(\Illuminate\Database\Eloquent\Builder $query, array $rawResult): void $callback
     * @param string|null $modelClass Model class to apply callback to (for multi-index searches)
     */
    public function modifyQuery(Closure $callback, ?string $modelClass = null): static
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $this->queryModifiers[$indexName][] = $callback;
        return $this;
    }

    public function clearQueryModifiers(?string $modelClass = null): static
    {
        if ($modelClass === null) {
            $this->queryModifiers = [];
        } else {
            $indexName = $this->resolveJoinedIndexName($modelClass);
            unset($this->queryModifiers[$indexName]);
        }
        return $this;
    }

    /**
     * Add a callback to modify the loaded Eloquent collection.
     *
     * @param Closure(\Illuminate\Database\Eloquent\Collection $models): \Illuminate\Database\Eloquent\Collection $callback
     * @param string|null $modelClass Model class to apply callback to (for multi-index searches)
     */
    public function modifyModels(Closure $callback, ?string $modelClass = null): static
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $this->modelModifiers[$indexName][] = $callback;
        return $this;
    }

    public function clearModelModifiers(?string $modelClass = null): static
    {
        if ($modelClass === null) {
            $this->modelModifiers = [];
        } else {
            $indexName = $this->resolveJoinedIndexName($modelClass);
            unset($this->modelModifiers[$indexName]);
        }
        return $this;
    }

    // ---- Introspection ----

    public function getQuery(): ?array
    {
        return $this->query !== null ? $this->deepCloneArray($this->query) : null;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function getFrom(): ?int
    {
        return $this->from;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getSource(): bool|string|array|null
    {
        return $this->source;
    }

    public function getHighlight(): array
    {
        return $this->highlight;
    }

    /** @return array<int|string, array<string, mixed>> */
    public function getAggregations(): array
    {
        return array_map(
            static fn(AggregationInterface|array $aggregation): array => $aggregation instanceof AggregationInterface
                ? $aggregation->toArray()
                : $aggregation,
            $this->aggregations,
        );
    }

    public function getPostFilter(): ?array
    {
        return $this->postFilter !== null ? $this->deepCloneArray($this->postFilter) : null;
    }

    public function getRescore(): array
    {
        return $this->rescore;
    }

    public function getSuggest(): array
    {
        return $this->suggest;
    }

    public function getCollapse(): array
    {
        return $this->collapse;
    }

    public function getTrackTotalHits(): int|bool|null
    {
        return $this->trackTotalHits;
    }

    public function getTrackScores(): ?bool
    {
        return $this->trackScores;
    }

    public function getMinScore(): ?float
    {
        return $this->minScore;
    }

    public function getSearchType(): ?string
    {
        return $this->searchType;
    }

    public function getPreference(): ?string
    {
        return $this->preference;
    }

    public function getPointInTime(): ?array
    {
        return $this->pointInTime;
    }

    public function getSearchAfter(): ?array
    {
        return $this->searchAfter;
    }

    public function getRouting(): ?array
    {
        return $this->routing;
    }

    public function getExplain(): ?bool
    {
        return $this->explain;
    }

    public function getTimeout(): ?string
    {
        return $this->timeout;
    }

    /** @return array<string, string> model class => index name */
    public function getIndexNames(): array
    {
        return $this->indexNames;
    }

    // ---- Clear all ----

    public function clearAll(): static
    {
        $this->query = null;
        $this->boolQuery = null;
        $this->highlight = [];
        $this->sort = [];
        $this->rescore = [];
        $this->from = null;
        $this->size = null;
        $this->suggest = [];
        $this->source = null;
        $this->collapse = [];
        $this->aggregations = [];
        $this->postFilter = null;
        $this->trackTotalHits = null;
        $this->trackScores = null;
        $this->minScore = null;
        $this->indicesBoost = [];
        $this->searchType = null;
        $this->preference = null;
        $this->pointInTime = null;
        $this->searchAfter = null;
        $this->routing = null;
        $this->explain = null;
        $this->terminateAfter = null;
        $this->requestCache = null;
        $this->scriptFields = null;
        $this->runtimeMappings = null;
        $this->timeout = null;
        $this->storedFields = null;
        $this->docvalueFields = null;
        $this->version = null;
        $this->knn = null;
        $this->queryModifiers = [];
        $this->modelModifiers = [];
        $this->relations = [];
        $this->softDeleteMode = SoftDeleteMode::ExcludeTrashed;
        return $this;
    }

    // ---- Build & Execute ----

    public function buildParams(): array
    {
        $params = [];

        if ($this->pointInTime !== null && ($this->routing !== null || $this->preference !== null)) {
            throw new LogicException(
                'routing() and preference() cannot be combined with pointInTime(): '
                . 'Elasticsearch takes them only when the point in time is opened.',
            );
        }

        if ($this->pointInTime === null) {
            $params['index'] = implode(',', array_values($this->indexNames));

            if ($this->preference !== null) {
                $params['preference'] = $this->preference;
            }

            if ($this->routing !== null) {
                $params['routing'] = implode(',', $this->routing);
            }
        }

        if ($this->searchType !== null) {
            $params['search_type'] = $this->searchType;
        }

        if ($this->requestCache !== null) {
            $params['request_cache'] = $this->requestCache;
        }

        $body = $this->buildBody();

        if ($body !== []) {
            $params['body'] = $body;
        }

        return $params;
    }

    public function execute(): SearchResult
    {
        $params = $this->buildParams();
        $rawResult = $this->engine->searchRaw($params);

        $modelResolver = $this->createModelResolver($rawResult);

        return new SearchResult(
            raw: $rawResult,
            modelResolver: $modelResolver->createResolver(),
            modelHydrationMismatchMode: $this->resolveModelHydrationMismatchMode(),
        );
    }

    public function paginate(
        int $perPage = self::DEFAULT_PAGE_SIZE,
        string $pageName = 'page',
        ?int $page = null,
    ): Paginator {
        if ($perPage < 1) {
            throw new InvalidArgumentException('perPage must be greater than 0.');
        }

        $page ??= Paginator::resolveCurrentPage($pageName);

        if ($page < 1) {
            throw new InvalidArgumentException('page must be greater than or equal to 1.');
        }

        if ($page > intdiv(PHP_INT_MAX, $perPage)) {
            throw new InvalidArgumentException('page is too large: the offset of its last hit does not fit in an integer.');
        }

        $builder = clone $this;
        $builder->clearSearchAfter();
        $builder->from(($page - 1) * $perPage);
        $builder->size($perPage);
        if ($builder->trackTotalHits === null) {
            $builder->trackTotalHits(true);
        }
        $searchResult = $builder->execute();

        return new Paginator(
            $searchResult,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        );
    }

    public function raw(): array
    {
        $params = $this->buildParams();
        return $this->engine->searchRaw($params);
    }

    public function first(): ?Hit
    {
        $result = (clone $this)->size(1)->execute();
        return $result->hits()->first();
    }

    public function firstOrFail(): Hit
    {
        $hit = $this->first();
        if ($hit === null) {
            throw new ModelNotFoundException('No search results found.');
        }
        return $hit;
    }

    public function count(): int
    {
        $params = (clone $this)
            ->size(0)
            ->trackTotalHits(true)
            ->clearSearchAfter()
            ->buildParams();

        $rawResult = $this->engine->searchRaw($params);

        return $rawResult['hits']['total']['value'] ?? 0;
    }

    public function deleteByQuery(): array
    {
        if (!$this->hasWriteQuery()) {
            throw new InvalidQueryException(
                'deleteByQuery requires an explicit query. Use Query::matchAll() to target all visible documents. '
                . 'When scout.soft_delete=true, call withTrashed() to include soft-deleted documents.',
            );
        }

        $params = ['index' => implode(',', array_values($this->indexNames))];

        if ($this->routing !== null) {
            $params['routing'] = implode(',', $this->routing);
        }

        $query = $this->buildFinalQuery();
        if ($query !== null) {
            $params['body']['query'] = $query;
        }

        return $this->engine->deleteByQueryRaw($params);
    }

    public function updateByQuery(array $script): array
    {
        if (!$this->hasWriteQuery()) {
            throw new InvalidQueryException(
                'updateByQuery requires an explicit query. Use Query::matchAll() to target all visible documents. '
                . 'When scout.soft_delete=true, call withTrashed() to include soft-deleted documents.',
            );
        }

        $params = ['index' => implode(',', array_values($this->indexNames))];

        if ($this->routing !== null) {
            $params['routing'] = implode(',', $this->routing);
        }

        $query = $this->buildFinalQuery();
        if ($query !== null) {
            $params['body']['query'] = $query;
        }

        $params['body']['script'] = $script;

        return $this->engine->updateByQueryRaw($params);
    }

    public function getEngine(): EngineInterface
    {
        return $this->engine;
    }

    /**
     * Get the Elasticsearch query as JSON string for debugging.
     */
    public function toJson(int $options = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->buildParams(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the Elasticsearch query parameters as array for debugging.
     */
    public function toArray(): array
    {
        return $this->buildParams();
    }

    public function cursor(int $chunkSize = 1000, string $keepAlive = '5m'): SearchCursor
    {
        if ($this->rescore !== []) {
            throw new LogicException(
                'cursor() and chunk() cannot be used with rescore(): they sort by _shard_doc, '
                . 'and Elasticsearch does not allow a sort with rescore.',
            );
        }

        $pageBuilder = clone $this;
        $pageBuilder->routing = null;
        $pageBuilder->preference = null;

        return new SearchCursor(
            $pageBuilder,
            $chunkSize,
            $keepAlive,
            $this->routing !== null ? implode(',', $this->routing) : null,
            $this->preference,
        );
    }

    public function chunk(int $chunkSize, callable $callback): void
    {
        $cursor = $this->cursor($chunkSize);
        $currentChunk = [];

        foreach ($cursor as $hit) {
            $currentChunk[] = $hit;

            if (count($currentChunk) === $chunkSize) {
                if ($callback($currentChunk) === false) {
                    return;
                }
                $currentChunk = [];
            }
        }

        if ($currentChunk !== []) {
            $callback($currentChunk);
        }
    }

    // ---- Deep clone ----

    public function __clone(): void
    {
        if ($this->query !== null) {
            $this->query = $this->deepCloneArray($this->query);
        }

        if ($this->boolQuery !== null) {
            $this->boolQuery = clone $this->boolQuery;
        }

        $this->sort = $this->deepCloneArray($this->sort);
        $this->rescore = $this->deepCloneArray($this->rescore);
        $this->highlight = $this->deepCloneArray($this->highlight);
        $this->aggregations = $this->deepCloneArray($this->aggregations);
        $this->collapse = $this->deepCloneArray($this->collapse);
        $this->suggest = $this->deepCloneArray($this->suggest);

        if ($this->postFilter !== null) {
            $this->postFilter = $this->deepCloneArray($this->postFilter);
        }

        if ($this->scriptFields !== null) {
            $this->scriptFields = $this->deepCloneArray($this->scriptFields);
        }

        if ($this->runtimeMappings !== null) {
            $this->runtimeMappings = $this->deepCloneArray($this->runtimeMappings);
        }

        if ($this->knn !== null) {
            $this->knn = $this->deepCloneArray($this->knn);
        }
    }

    /** @param array<mixed> $arr */
    private function deepCloneArray(array $arr): array
    {
        return array_map(
            fn($item) => is_object($item)
                ? clone $item
                : (is_array($item) ? $this->deepCloneArray($item) : $item),
            $arr,
        );
    }

    // ---- Private helpers ----

    /**
     * @param QueryInterface|Closure():(QueryInterface|array<string, mixed>)|array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function resolveQueryToArray(QueryInterface|Closure|array $query, string $name): array
    {
        if ($query instanceof Closure) {
            $query = $query();
        }

        $resolved = $query instanceof QueryInterface ? $query->toArray() : $query;

        if ($resolved === []) {
            throw new InvalidQueryException("$name cannot be empty");
        }

        return $resolved;
    }

    /** @param array<QueryInterface|Closure|array<string, mixed>> $queries */
    private function refuseEmptyClauses(string $method, array $queries): void
    {
        if (in_array([], $queries, true)) {
            throw new InvalidQueryException("$method() clause cannot be empty");
        }
    }

    private function buildBody(): array
    {
        $this->validateSearchAfterUsage();

        $body = [];

        $query = $this->knn !== null && $this->query === null && !$this->boolQuery?->hasClauses()
            ? null
            : $this->buildFinalQuery();
        if ($query !== null) {
            $body['query'] = $query;
        }

        if ($this->knn !== null) {
            $body['knn'] = $this->buildKnn($this->knn);
        }

        if ($this->highlight !== []) {
            $body['highlight'] = $this->highlight;
        }

        if ($this->sort !== []) {
            $body['sort'] = $this->sort;
        }

        if ($this->rescore !== []) {
            $body['rescore'] = $this->rescore;
        }

        if ($this->from !== null) {
            $body['from'] = $this->from;
        }

        if ($this->size !== null) {
            $body['size'] = $this->size;
        }

        if ($this->suggest !== []) {
            $body['suggest'] = $this->suggest;
        }

        if ($this->source !== null) {
            $body['_source'] = $this->source;
        }

        if ($this->collapse !== []) {
            $body['collapse'] = $this->collapse;
        }

        if ($this->aggregations !== []) {
            $body['aggs'] = $this->getAggregations();
        }

        if ($this->postFilter !== null) {
            $body['post_filter'] = $this->postFilter;
        }

        if ($this->trackTotalHits !== null) {
            $body['track_total_hits'] = $this->trackTotalHits;
        }

        if ($this->trackScores !== null) {
            $body['track_scores'] = $this->trackScores;
        }

        if ($this->minScore !== null) {
            $body['min_score'] = $this->minScore;
        }

        if ($this->indicesBoost !== []) {
            $body['indices_boost'] = $this->indicesBoost;
        }

        if ($this->pointInTime !== null) {
            $body['pit'] = $this->pointInTime;
        }

        if ($this->searchAfter !== null) {
            $body['search_after'] = $this->searchAfter;
        }

        if ($this->explain !== null) {
            $body['explain'] = $this->explain;
        }

        if ($this->terminateAfter !== null) {
            $body['terminate_after'] = $this->terminateAfter;
        }

        if ($this->scriptFields !== null) {
            $body['script_fields'] = $this->scriptFields;
        }

        if ($this->runtimeMappings !== null) {
            $body['runtime_mappings'] = $this->runtimeMappings;
        }

        if ($this->timeout !== null) {
            $body['timeout'] = $this->timeout;
        }

        if ($this->storedFields !== null) {
            $body['stored_fields'] = $this->storedFields;
        }

        if ($this->docvalueFields !== null) {
            $body['docvalue_fields'] = $this->docvalueFields;
        }

        if ($this->version !== null) {
            $body['version'] = $this->version;
        }

        return $body;
    }

    private function buildFinalQuery(): ?array
    {
        $boolQuery = $this->boolQuery?->hasClauses() ? $this->boolQuery : null;
        $softDeleteFilter = $this->buildSoftDeleteFilter();

        if ($boolQuery !== null) {
            if ($this->query === null && $softDeleteFilter === null) {
                return $boolQuery->toArray();
            }

            $merged = clone $boolQuery;

            if ($this->query !== null) {
                $merged->addMust($this->query);
            }

            return ($softDeleteFilter !== null ? $this->withSoftDeleteFilter($merged, $softDeleteFilter) : $merged)->toArray();
        }

        if ($softDeleteFilter !== null) {
            $bool = new BoolQuery();
            if ($this->query !== null) {
                $bool->addMust($this->query);
            }
            $bool->addFilter($softDeleteFilter);
            return $bool->toArray();
        }

        return $this->query;
    }

    /**
     * Adds the soft delete filter without changing what the query matches: a
     * bool of should clauses alone requires one of them to match, which a
     * filter clause beside them would turn off, so such a bool is wrapped.
     */
    private function withSoftDeleteFilter(BoolQuery $bool, QueryInterface|array $softDeleteFilter): BoolQuery
    {
        if ($bool->getShouldClauses() !== [] && $bool->getMustClauses() === [] && $bool->getFilterClauses() === []) {
            return (new BoolQuery())->addMust($bool)->addFilter($softDeleteFilter);
        }

        return $bool->addFilter($softDeleteFilter);
    }

    /**
     * A top-level knn search is combined with the query as a disjunction, so
     * the soft delete filter has to restrict every knn entry itself.
     *
     * @param array<mixed> $knn
     * @return array<mixed>
     */
    private function buildKnn(array $knn): array
    {
        $softDeleteFilter = $this->buildSoftDeleteFilter();

        if ($softDeleteFilter === null) {
            return $knn;
        }

        $filter = $softDeleteFilter instanceof QueryInterface ? $softDeleteFilter->toArray() : $softDeleteFilter;
        $withFilter = static function (array $entry) use ($filter): array {
            $existing = $entry['filter'] ?? [];
            $entry['filter'] = [...(is_array($existing) && array_is_list($existing) ? $existing : [$existing]), $filter];

            return $entry;
        };

        return array_is_list($knn) ? array_map($withFilter, $knn) : $withFilter($knn);
    }

    private function buildSoftDeleteFilter(): QueryInterface|array|null
    {
        if (!$this->isSoftDeleteEnabled()) {
            return null;
        }

        return match ($this->softDeleteMode) {
            SoftDeleteMode::WithTrashed => null,
            SoftDeleteMode::OnlyTrashed => new TermQuery('__soft_deleted', 1),
            SoftDeleteMode::ExcludeTrashed => [
                'bool' => [
                    'should' => [
                        ['term' => ['__soft_deleted' => ['value' => 0]]],
                        ['bool' => ['must_not' => [['exists' => ['field' => '__soft_deleted']]]]],
                    ],
                    'minimum_should_match' => 1,
                ],
            ],
        };
    }

    private function isSoftDeleteEnabled(): bool
    {
        try {
            return (bool) config('scout.soft_delete', false);
        } catch (Throwable) {
            // Allow using SearchBuilder in unit contexts where Laravel config container is unavailable.
            return false;
        }
    }

    private function hasWriteQuery(): bool
    {
        return $this->query !== null || ($this->boolQuery?->hasClauses() ?? false);
    }

    private function resolveJoinedIndexName(?string $modelClass): string
    {
        if ($modelClass !== null) {
            if (isset($this->indexNames[$modelClass])) {
                return $this->indexNames[$modelClass];
            }

            throw new ModelNotJoinedException($modelClass);
        }

        if (count($this->indexNames) > 1) {
            throw new InvalidQueryException(
                'Model class is required when multiple indices are joined. '
                . 'Pass $modelClass to with(), modifyQuery(), or modifyModels().',
            );
        }

        return array_values($this->indexNames)[0];
    }

    private function createModelResolver(array $rawResult): ModelResolver
    {
        $withTrashed = $this->softDeleteMode !== SoftDeleteMode::ExcludeTrashed;

        $resolver = new ModelResolver(
            $this->aliasRegistry,
            $rawResult['hits']['hits'] ?? [],
            $rawResult['suggest'] ?? [],
            $rawResult,
        );

        foreach ($this->indexNames as $modelClass => $indexName) {
            $resolver->registerIndex(
                indexName: $indexName,
                modelClass: $modelClass,
                relations: $this->relations[$indexName] ?? [],
                queryCallbacks: $this->queryModifiers[$indexName] ?? [],
                collectionCallbacks: $this->modelModifiers[$indexName] ?? [],
                withTrashed: $withTrashed,
            );
        }

        return $resolver;
    }

    private function resolveEffectiveConnectionName(?string $connection): string
    {
        if ($connection !== null && $connection !== '') {
            return $connection;
        }

        try {
            return (string) config('elastic.client.default', 'default');
        } catch (Throwable) {
            return 'default';
        }
    }

    private function validateSearchAfterUsage(): void
    {
        if ($this->searchAfter === null) {
            return;
        }

        if ($this->from === null || $this->from === 0) {
            return;
        }

        throw new InvalidQueryException(
            'searchAfter cannot be used with from > 0. Set from(0) or clearSearchAfter().',
        );
    }

    private function resolveModelHydrationMismatchMode(): string
    {
        try {
            return ConfigOption::oneOf(
                'elastic.scout.model_hydration_mismatch',
                [
                    SearchResult::HYDRATION_MISMATCH_IGNORE,
                    SearchResult::HYDRATION_MISMATCH_LOG,
                    SearchResult::HYDRATION_MISMATCH_EXCEPTION,
                ],
                SearchResult::HYDRATION_MISMATCH_IGNORE,
            );
        } catch (BindingResolutionException) {
            return SearchResult::HYDRATION_MISMATCH_IGNORE;
        }
    }
}
