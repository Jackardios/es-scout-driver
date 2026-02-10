<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Search;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Traits\Conditionable;
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
use Jackardios\EsScoutDriver\Query\Concerns\ResolvesQueries;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Sort\SortInterface;
use stdClass;
use Throwable;

class SearchBuilder
{
    use Conditionable;
    use ResolvesQueries;

    public const DEFAULT_PAGE_SIZE = 10;

    private EngineInterface $engine;
    private AliasRegistry $aliasRegistry;
    private ?string $joinedConnectionName = null;
    private ?string $baseModelClass = null;

    /** @var array<string, string> model class => index name */
    private array $indexNames = [];

    private ?array $query = null;
    private ?BoolQuery $boolQuery = null;
    private array $highlight = [];
    private array $sort = [];
    private array $rescore = [];
    private ?int $from = null;
    private ?int $size = null;
    private array $suggest = [];
    private bool|string|array|null $source = null;
    private array $collapse = [];
    private array $aggregations = [];
    private ?array $postFilter = null;
    private int|bool|null $trackTotalHits = null;
    private ?bool $trackScores = null;
    private ?float $minScore = null;
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

    /** @var array<string, array> index => relations */
    private array $relations = [];

    /** @param QueryInterface|Closure|array|null $query */
    public function __construct(Model $model, $query = null)
    {
        $this->engine = $model->searchableUsing();
        $this->aliasRegistry = new AliasRegistry($this->engine->getClient());

        $this->join(get_class($model));

        if ($query !== null) {
            $this->query($query);
        }
    }

    // ---- Query methods ----

    /** @param QueryInterface|Closure|array $query */
    public function query($query): self
    {
        $resolved = $this->resolveQueryToArray($query);

        if ($resolved === []) {
            throw new InvalidQueryException('Search query cannot be empty');
        }

        $this->query = $resolved;
        return $this;
    }

    public function clearQuery(): self
    {
        $this->query = null;
        return $this;
    }

    // ---- Bool query shortcuts (variadic add methods) ----

    /** @param QueryInterface|Closure|array ...$queries */
    public function must(QueryInterface|Closure|array ...$queries): self
    {
        $this->boolQuery()->addMustMany(...array_map(fn($q) => $this->resolveQueryObject($q), $queries));
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function mustNot(QueryInterface|Closure|array ...$queries): self
    {
        $this->boolQuery()->addMustNotMany(...array_map(fn($q) => $this->resolveQueryObject($q), $queries));
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function should(QueryInterface|Closure|array ...$queries): self
    {
        $this->boolQuery()->addShouldMany(...array_map(fn($q) => $this->resolveQueryObject($q), $queries));
        return $this;
    }

    /** @param QueryInterface|Closure|array ...$queries */
    public function filter(QueryInterface|Closure|array ...$queries): self
    {
        $this->boolQuery()->addFilterMany(...array_map(fn($q) => $this->resolveQueryObject($q), $queries));
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

    public function clearBoolQuery(): self
    {
        $this->boolQuery = null;
        return $this;
    }

    // ---- Highlight ----

    public function highlightRaw(array $highlight): self
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
    ): self {
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
    ): self {
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

    public function clearHighlight(): self
    {
        $this->highlight = [];
        return $this;
    }

    // ---- Sort ----

    public function sortRaw(array $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function sort(
        string|SortInterface $field,
        SortOrder|string $direction = 'asc',
        string|int|float|bool|null $missing = null,
        ?string $mode = null,
        ?string $unmappedType = null,
    ): self {
        if ($field instanceof SortInterface) {
            $this->sort[] = $field->toArray();
            return $this;
        }

        $order = $direction instanceof SortOrder ? $direction->value : $direction;

        if ($missing === null && $mode === null && $unmappedType === null) {
            $this->sort[] = [$field => $order];
            return $this;
        }

        $sortConfig = ['order' => $order];
        if ($missing !== null) {
            $sortConfig['missing'] = $missing;
        }
        if ($mode !== null) {
            $sortConfig['mode'] = $mode;
        }
        if ($unmappedType !== null) {
            $sortConfig['unmapped_type'] = $unmappedType;
        }
        $this->sort[] = [$field => $sortConfig];
        return $this;
    }

    public function clearSort(): self
    {
        $this->sort = [];
        return $this;
    }

    // ---- Rescore ----

    public function rescoreRaw(array $rescore): self
    {
        $this->rescore = $rescore;
        return $this;
    }

    /** @param QueryInterface|Closure|array $query */
    public function rescore($query, ?int $windowSize = null, ?float $queryWeight = null, ?float $rescoreQueryWeight = null): self
    {
        $this->rescore['query']['rescore_query'] = $this->resolveQueryToArray($query);

        if ($windowSize !== null) {
            $this->rescore['window_size'] = $windowSize;
        }

        if ($queryWeight !== null) {
            $this->rescore['query']['query_weight'] = $queryWeight;
        }

        if ($rescoreQueryWeight !== null) {
            $this->rescore['query']['rescore_query_weight'] = $rescoreQueryWeight;
        }

        return $this;
    }

    public function clearRescore(): self
    {
        $this->rescore = [];
        return $this;
    }

    // ---- Pagination ----

    public function from(int $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function size(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    // ---- Suggest ----

    public function suggestRaw(array $suggest): self
    {
        $this->suggest = $suggest;
        return $this;
    }

    public function suggest(string $name, array $definition): self
    {
        $this->suggest[$name] = $definition;
        return $this;
    }

    public function clearSuggest(): self
    {
        $this->suggest = [];
        return $this;
    }

    // ---- Source ----

    public function sourceRaw(bool|string|array $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function source(array $includes, ?array $excludes = null): self
    {
        if ($excludes !== null) {
            $this->source = ['includes' => $includes, 'excludes' => $excludes];
        } else {
            $this->source = $includes;
        }

        return $this;
    }

    public function clearSource(): self
    {
        $this->source = null;
        return $this;
    }

    // ---- Collapse ----

    public function collapseRaw(array $collapse): self
    {
        $this->collapse = $collapse;
        return $this;
    }

    public function collapse(string $field): self
    {
        $this->collapse = ['field' => $field];
        return $this;
    }

    public function clearCollapse(): self
    {
        $this->collapse = [];
        return $this;
    }

    // ---- Aggregate ----

    public function aggregateRaw(array $aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function aggregate(string $name, AggregationInterface|array $definition): self
    {
        $this->aggregations[$name] = $definition instanceof AggregationInterface
            ? $definition->toArray()
            : $definition;
        return $this;
    }

    public function clearAggregations(): self
    {
        $this->aggregations = [];
        return $this;
    }

    // ---- Post Filter ----

    /** @param QueryInterface|Closure|array $query */
    public function postFilter($query): self
    {
        $this->postFilter = $this->resolveQueryToArray($query);
        return $this;
    }

    public function clearPostFilter(): self
    {
        $this->postFilter = null;
        return $this;
    }

    // ---- Track & Score ----

    public function trackTotalHits(int|bool $trackTotalHits): self
    {
        $this->trackTotalHits = $trackTotalHits;
        return $this;
    }

    public function trackScores(bool $trackScores): self
    {
        $this->trackScores = $trackScores;
        return $this;
    }

    public function minScore(float $minScore): self
    {
        $this->minScore = $minScore;
        return $this;
    }

    // ---- Search config ----

    public function searchType(string $searchType): self
    {
        $this->searchType = $searchType;
        return $this;
    }

    public function preference(string $preference): self
    {
        $this->preference = $preference;
        return $this;
    }

    public function pointInTime(string $id, ?string $keepAlive = null): self
    {
        $this->pointInTime = ['id' => $id];

        if ($keepAlive !== null) {
            $this->pointInTime['keep_alive'] = $keepAlive;
        }

        return $this;
    }

    public function clearPointInTime(): self
    {
        $this->pointInTime = null;
        return $this;
    }

    public function searchAfter(array $searchAfter): self
    {
        $this->searchAfter = $searchAfter;
        return $this;
    }

    public function clearSearchAfter(): self
    {
        $this->searchAfter = null;
        return $this;
    }

    public function routing(string|int|array|null $routing): self
    {
        $this->routing = match (true) {
            $routing === null => null,
            is_array($routing) => $routing,
            default => [(string) $routing],
        };
        return $this;
    }

    public function clearRouting(): self
    {
        $this->routing = null;
        return $this;
    }

    public function explain(bool $explain): self
    {
        $this->explain = $explain;
        return $this;
    }

    public function terminateAfter(int $terminateAfter): self
    {
        $this->terminateAfter = $terminateAfter;
        return $this;
    }

    public function requestCache(bool $requestCache): self
    {
        $this->requestCache = $requestCache;
        return $this;
    }

    // ---- Search options ----

    public function timeout(string $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /** @param array<int, string> $fields */
    public function storedFields(array $fields): self
    {
        $this->storedFields = $fields;
        return $this;
    }

    /** @param array<int, string|array<string, mixed>> $fields */
    public function docvalueFields(array $fields): self
    {
        $this->docvalueFields = $fields;
        return $this;
    }

    public function version(bool $version = true): self
    {
        $this->version = $version;
        return $this;
    }

    public function scriptFields(array $scriptFields): self
    {
        $this->scriptFields = $scriptFields;
        return $this;
    }

    public function runtimeMappings(array $runtimeMappings): self
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
    ): self {
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

    public function knnRaw(array $knn): self
    {
        $this->knn = $knn;
        return $this;
    }

    public function clearKnn(): self
    {
        $this->knn = null;
        return $this;
    }

    public function getKnn(): ?array
    {
        return $this->knn;
    }

    // ---- Join & Load ----

    public function join(string $modelClass, ?float $boost = null): self
    {
        if (
            !is_a($modelClass, Model::class, true)
            || !in_array(\Jackardios\EsScoutDriver\Searchable::class, class_uses_recursive($modelClass), true)
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

    public function clearIndicesBoost(): self
    {
        $this->indicesBoost = [];
        return $this;
    }

    /**
     * Eager load relations on the models.
     *
     * @param array<string> $relations Relations to eager load
     * @param string|null $modelClass Model class to apply relations to (for multi-index searches)
     */
    public function with(array $relations, ?string $modelClass = null): self
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $this->relations[$indexName] = array_merge($this->relations[$indexName] ?? [], $relations);
        return $this;
    }

    // ---- Eloquent callbacks (new concise API) ----

    /**
     * Add a callback to modify the Eloquent query before loading models.
     *
     * @param Closure(\Illuminate\Database\Eloquent\Builder $query, array $rawResult): void $callback
     * @param string|null $modelClass Model class to apply callback to (for multi-index searches)
     */
    public function modifyQuery(Closure $callback, ?string $modelClass = null): self
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $this->queryModifiers[$indexName][] = $callback;
        return $this;
    }

    public function clearQueryModifiers(?string $modelClass = null): self
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
    public function modifyModels(Closure $callback, ?string $modelClass = null): self
    {
        $indexName = $this->resolveJoinedIndexName($modelClass);
        $this->modelModifiers[$indexName][] = $callback;
        return $this;
    }

    public function clearModelModifiers(?string $modelClass = null): self
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
        return $this->query;
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

    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getPostFilter(): ?array
    {
        return $this->postFilter;
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

    public function clearAll(): self
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
        return $this;
    }

    // ---- Build & Execute ----

    public function buildParams(): array
    {
        $params = [];

        if ($this->pointInTime !== null) {
            $params['pit'] = $this->pointInTime;
        } else {
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

        return new SearchResult($rawResult, $modelResolver->createResolver());
    }

    public function paginate(
        int $perPage = self::DEFAULT_PAGE_SIZE,
        string $pageName = 'page',
        ?int $page = null,
    ): Paginator {
        $page ??= Paginator::resolveCurrentPage($pageName);

        $builder = clone $this;
        $builder->from(($page - 1) * $perPage);
        $builder->size($perPage);
        $builder->trackTotalHits(true);
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
                . 'When scout.soft_delete=true, call boolQuery()->withTrashed() to include soft-deleted documents.',
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
                . 'When scout.soft_delete=true, call boolQuery()->withTrashed() to include soft-deleted documents.',
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
        return new SearchCursor($this, $chunkSize, $keepAlive);
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
        // AliasRegistry is intentionally shared between clones (singleton-like, caches alias lookups)

        if ($this->boolQuery !== null) {
            $this->boolQuery = clone $this->boolQuery;
        }

        // Deep clone arrays containing QueryInterface objects or other mutable structures
        $this->sort = $this->deepCloneArray($this->sort);
        $this->rescore = $this->deepCloneArray($this->rescore);
        $this->highlight = $this->deepCloneArray($this->highlight);
        $this->aggregations = $this->deepCloneArray($this->aggregations);
        $this->collapse = $this->deepCloneArray($this->collapse);
        $this->suggest = $this->deepCloneArray($this->suggest);

        if ($this->pointInTime !== null) {
            $this->pointInTime = $this->deepCloneArray($this->pointInTime);
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
            fn($item) => $item instanceof QueryInterface ? clone $item : (is_array($item) ? $this->deepCloneArray($item) : $item),
            $arr,
        );
    }

    // ---- Private helpers ----

    private function buildBody(): array
    {
        $body = [];

        $query = $this->buildFinalQuery();
        if ($query !== null) {
            $body['query'] = $query;
        }

        if ($this->knn !== null) {
            $body['knn'] = $this->knn;
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
            $body['aggs'] = $this->aggregations;
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
        $hasBoolClauses = $this->boolQuery !== null && $this->boolQuery->hasClauses();
        $softDeleteFilter = $this->buildSoftDeleteFilter();

        if ($this->query !== null && $hasBoolClauses) {
            // Both set: wrap $this->query into must of a cloned BoolQuery to avoid mutation
            $merged = clone $this->boolQuery;
            $merged->addMust($this->query);
            if ($softDeleteFilter !== null) {
                $merged->addFilter($softDeleteFilter);
            }
            return $merged->toArray();
        }

        if ($hasBoolClauses) {
            if ($softDeleteFilter !== null) {
                $merged = clone $this->boolQuery;
                $merged->addFilter($softDeleteFilter);
                return $merged->toArray();
            }
            return $this->boolQuery->toArray();
        }

        // If we have a soft delete filter but no bool query, wrap the query in a bool query
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

    private function buildSoftDeleteFilter(): QueryInterface|array|null
    {
        if (!$this->isSoftDeleteEnabled()) {
            return null;
        }

        $softDeleteMode = $this->boolQuery?->getSoftDeleteMode() ?? SoftDeleteMode::ExcludeTrashed;

        return match ($softDeleteMode) {
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
        return ($this->query !== null && $this->query !== [])
            || ($this->boolQuery !== null && $this->boolQuery->hasClauses());
    }

    private function resolveJoinedIndexName(?string $modelClass): string
    {
        if ($modelClass !== null) {
            if (isset($this->indexNames[$modelClass])) {
                return $this->indexNames[$modelClass];
            }

            throw new ModelNotJoinedException($modelClass);
        }

        return array_values($this->indexNames)[0];
    }

    private function createModelResolver(array $rawResult): ModelResolver
    {
        $softDeleteMode = $this->boolQuery?->getSoftDeleteMode() ?? SoftDeleteMode::ExcludeTrashed;
        $withTrashed = $softDeleteMode !== SoftDeleteMode::ExcludeTrashed;

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
}
