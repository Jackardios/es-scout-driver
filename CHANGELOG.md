# Changelog

## Unreleased (1.0.0-rc.2)

Changes since the `v1.0.0-rc.1` tag.

### Upgrading from 1.0.0-rc.1

- `QueryStringQuery::maxExpansions()` and `prefixLength()` are removed: `query_string` rejects `max_expansions` and
  `prefix_length`. Call `fuzzyMaxExpansions()` and `fuzzyPrefixLength()`.
- `KnnQuery::innerHits()`, `TextExpansionQuery::prune()` and `WildcardQuery::wildcard()` are removed. The `knn` and
  `text_expansion` queries reject `inner_hits` and `prune`; `wildcard()` set the pattern a second way that clashed with
  `value`.
- `EngineInterface::countRaw()` is removed, and `openPointInTime()` takes `$routing` and `$preference`. Implementations
  of the interface drop the one and add the others.
- `BulkOperationException`, `NotSearchableModelException` and `ModelNotJoinedException` are final.
- `Query\Concerns\ResolvesQueries` and `HasFunctionScoreMode` are removed, and the unused `ModelResolver::withRawData()`,
  `preloadAll()` and `getCachedModels()` with them.
- Unknown values of `model_hydration_mismatch`, `bulk_failure_mode` and `scout_query_type` throw
  `InvalidArgumentException`; `model_hydration_mismatch` used to fall back to `ignore`.

### Added

- `Searchable::openPointInTime()`, `EngineInterface::openPointInTime()` take `$routing` and `$preference`;
  `cursor()` and `chunk()` open their point in time with the builder's routing and preference.
- `QueryStringQuery::fuzzyMaxExpansions()`, `fuzzyPrefixLength()` and `fuzzyTranspositions()`, shared with
  `simple_query_string`.
- `DateHistogramAggregation::calendarInterval()`.
- `TopHitsAggregation::sort()` takes a `SortInterface` or a `SortOrder`; aggregation orders take `SortOrder` and
  `GeoDistanceSort::distanceType()` takes `DistanceType`.
- A `sparse_vector` query text without `inferenceId`, for `semantic_text` fields.
- A query object as a `function_score` function filter.
- A Scout search callback may return the client's response instead of its array.
- Hits of an alias, data stream, wildcard or comma-separated `searchableAs()` are resolved to their model.

### Changed

- A Scout `whereIn()` with an empty list matches nothing, like Scout's other engines.
- Scout searches with `scout.soft_delete` match documents without `__soft_deleted`, like `SearchBuilder`.
- Indexing leaves out the hit metadata (`_id`, `_index`, `_score`...) a searched model carries; the rest of its Scout
  metadata is indexed as before.
- `KnnQuery::numCandidates()` refuses a value above 10000, and the default of twice `k` (at least 100) is capped at
  10000; a `k` above 10000 sends no `num_candidates`.
- `InvalidQueryException` for a boosting query without `negativeBoost`, a pinned query without exactly one of `ids` and
  `docs`, a `sparse_vector` `queryVector` beside `query` or `inferenceId`, an empty post filter, rescore or bool clause
  query, and a query closure that returns something other than a query or an array.
- `InvalidArgumentException` for a terms or composite size below 1, a histogram interval of 0 or less, empty
  percentiles or one outside 0..100, a negative precision threshold or sigma, a duplicate composite source, and an
  aggregation, sub-aggregation or filters-aggregation name that would send the names as a JSON list.
- `sort()` refuses a direction or options beside a `SortInterface`; its `$direction` defaults to `null` (ascending for a
  field name).
- `cursor()` and `chunk()` refuse `rescore()`; a manual `pointInTime()` refuses `routing()` and `preference()`.
- `aggregate()` keeps aggregation objects until the request is built; a clone of the builder copies them, their
  sub-aggregations and their filter queries.
- `count()` leaves out aggregations, suggesters, highlight, sort, rescore and collapse.
- `bulk_failure_mode`, `scout_query_type` and `model_hydration_mismatch` are trimmed and case-insensitive.
- `BulkOperationException` names the type and reason of the first failure.
- Scout `paginate()` refuses a page or page size below 1 and a page whose offset overflows an integer.
- An empty `searchableConnection()` means the default connection; an unknown connection throws
  `InvalidArgumentException` naming it. The connection config is read when a client is first resolved.
- The `RemoveFromSearch` job takes the `scout.jobs` options, like Scout's own jobs.
- The aggregation `missing()` takes any scalar; histogram offsets and percentiles compression take floats; range
  aggregation bounds take date strings.

### Fixed

- Point-in-time searches (`pointInTime()`, `cursor()`, `chunk()`) sent no point in time, which the client dropped, and
  searched every index.
- Saving a model found by a search failed: its hit metadata was indexed and Elasticsearch rejected `_index`.
- With `scout.soft_delete`, a top-level knn search added every non-trashed document; the soft-delete filter now goes
  into `knn.filter`.
- `with()` keeps keyed relations and their constraint closures.
- `rescore()` no longer keeps the window size and weights of an earlier call.
- The cursor detects a `_shard_doc` sort given as a string and no longer adds a second one.
- `fixedInterval()` sent both date histogram intervals (400); an option-less `top_hits` was sent as `[]` (400); a
  one-element terms `include`/`exclude` array was sent as a regular expression; `missing(0)` threw a `TypeError`.
- A numeric bool clause key collided with the positional clauses; non-sequential lists (terms, ids, fields, dis_max,
  pinned, knn vector, more_like_this) were sent as JSON objects; an empty `function_score` was sent as `[]`.
- Hydrating search results qualifies the Scout key column, so a join in `modifyQuery()` is not ambiguous.
- `scout:index --key` no longer sends `primaryKey` in the create index body.

## Unreleased (1.0.0-rc.1)

The 1.x line requires Laravel 12 or 13. Laravel 10 and 11 stay on 0.x.

### Upgrading from 0.x

- Requirements: PHP 8.2, Laravel 12 or 13, Scout 10.24 or 11.
- `BoolQuery::addMustMany()`, `addMustNotMany()`, `addShouldMany()` and `addFilterMany()` are removed. Call
  `must()`, `mustNot()`, `should()` and `filter()`, which take the same arguments.
- The soft-delete mode moves from `BoolQuery` to `SearchBuilder`: replace `$builder->boolQuery()->withTrashed()` with
  `$builder->withTrashed()`, and the same for `onlyTrashed()`, `excludeTrashed()`, `softDelete()` and
  `getSoftDeleteMode()`.
- `Engine` is final and its members are private. Implement `EngineInterface` or wrap the engine instead of extending
  it.
- `SearchBuilder::__construct()`, `query()`, `rescore()`, `postFilter()` and `Searchable::searchQuery()` type their
  `$query` parameter as `QueryInterface|Closure|array` (nullable in the constructor and `searchQuery()`). Other values
  throw a `TypeError` at the call.
- A closure given to `BoolQuery`'s `addMust()`, `must()` and the other clause methods, or to the same `SearchBuilder`
  methods, is called without arguments, like every other query closure. It used to receive the bool query itself,
  and returning it made the query contain itself.
- Classes and methods marked `@internal` are outside the compatibility promise; see "Backward Compatibility" in the
  README.

### Added

- `BoolQuery::must()`, `mustNot()`, `should()` and `filter()` append any number of clauses, like their `SearchBuilder`
  counterparts.
- `SearchBuilder::softDelete()`, `withTrashed()`, `onlyTrashed()`, `excludeTrashed()` and `getSoftDeleteMode()`.
- `PrefixQuery::boost()` and `ExistsQuery::boost()`.
- `dev-main` is aliased `1.x-dev`, so dependants can require `^1.0@dev` instead of `dev-main`.

### Fixed

- `SearchBuilder::clearBoolQuery()` no longer resets the soft-delete mode to excluding trashed documents;
  `clearAll()` still does.
- `paginate()` throws `InvalidArgumentException` for a page whose offset does not fit in an integer instead of a
  `TypeError`.
- An empty `BoolQuery` keeps its boost: it is sent as `match_all` with the boost instead of dropping it.
- `BoolQuery::hasClause()` and `getClause()` throw `InvalidArgumentException` for a section other than `must`,
  `must_not`, `should` or `filter` (a typo such as `mustNot` returned false or null).

### Changed

- `Engine::getClient()` returns `Client` (the interface still allows `null` for `NullEngine`).
- CI covers PHP 8.2–8.5 on Laravel 12 and 13, lowest dependencies, Elasticsearch 8.19.22 and 9.5.3, a weekly run and
  `composer audit`.

## Unreleased (0.1.0)

First tagged release, cut from `bb72a59`. Supports Laravel 10–13 and Scout 10–11.

### Fixed

- Laravel 13: the `elastic` driver no longer fails with `Undefined property: EngineManager::$app`. Laravel 13 binds
  custom driver closures to the manager.
- Scout 11: `where()` clauses, including the `__soft_deleted` constraint Scout adds for soft-deleting models, build
  valid queries again. Scout 11 stores them as `field`/`operator`/`value` entries.
- The soft-delete filter no longer turns off the should clauses of a bool query that has no must or filter clause: such
  a query matched every document that was not trashed, and `deleteByQuery()`/`updateByQuery()` acted on all of them.
  The bool is wrapped in a must clause when a filter beside it would change what it matches.

### Added

- Scout 11 `where()` operators: `=`, `!=`/`<>`, `>`, `>=`, `<`, `<=`; dates are sent as ISO 8601.

### Changed

- The Illuminate components in use are required at Laravel 10–13, so Composer no longer installs the package on
  Laravel 9.
- `where($field, null)` matches documents without the field (`!=` matches documents with it) instead of sending a
  `term` query for `null`. Other operators, such as `like`, throw `InvalidArgumentException`.
- `laravel/scout` no longer allows the unreleased `^12.0`.
