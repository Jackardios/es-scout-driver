# Migration Guide: elastic-scout-driver-plus → es-scout-driver

## Requirements & Dependencies

| | OLD (elastic-scout-driver-plus) | NEW (es-scout-driver) |
|---|---|---|
| **PHP** | ^7.4 \|\| ^8.0 | ^8.1 |
| **Laravel** | 8-10 | 10-12 |
| **Elasticsearch** | 7.x-8.x | 8.x-9.x |
| **Dependencies** | babenkoivan/elastic-scout-driver, babenkoivan/elastic-adapter | elasticsearch/elasticsearch (official) |

**Important**: The new package uses the official Elasticsearch PHP client directly, removing the dependency on `elastic-scout-driver` and `elastic-adapter`.

### Composer Migration

```bash
# Remove old packages
composer remove jackardios/elastic-scout-driver-plus babenkoivan/elastic-scout-driver babenkoivan/elastic-adapter

# Install new package
composer require jackardios/es-scout-driver
```

---

## Namespace Change

```php
// OLD
use Elastic\ScoutDriverPlus\Searchable;
use Elastic\ScoutDriverPlus\Support\Query;
use Elastic\ScoutDriverPlus\Paginator;
use Elastic\ScoutDriverPlus\Decorators\SearchResult;
use Elastic\ScoutDriverPlus\Decorators\Hit;

// NEW
use Jackardios\EsScoutDriver\Searchable;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\EsScoutDriver\Search\Paginator;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\EsScoutDriver\Search\Hit;
```

---

## Query API Changes

### Query Factory: Constructor Arguments vs Fluent Methods

**OLD**: Empty constructor + fluent builder
**NEW**: Required params in constructor, optional via fluent setters

```php
// OLD
Query::match()->field('title')->query('laravel')
Query::term()->field('status')->value('published')
Query::terms()->field('tags')->values(['php', 'laravel'])
Query::range()->field('price')->gte(100)->lte(500)
Query::multiMatch()->fields(['title', 'body'])->query('search')
Query::geoDistance()->field('location')->lat(40.7)->lon(-74.0)->distance('10km')
Query::nested()->path('comments')->query(...)
Query::exists()->field('author')
Query::prefix()->field('name')->value('lar')
Query::wildcard()->field('name')->value('lar*')
Query::regexp()->field('name')->value('lar.*')
Query::fuzzy()->field('name')->value('larvel')
Query::ids()->values(['1', '2', '3'])
Query::matchPhrase()->field('title')->query('laravel framework')
Query::matchPhrasePrefix()->field('title')->query('laravel fra')

// NEW
Query::match('title', 'laravel')
Query::term('status', 'published')
Query::terms('tags', ['php', 'laravel'])
Query::range('price')->gte(100)->lte(500)
Query::multiMatch(['title', 'body'], 'search')
Query::geoDistance('location', 40.7, -74.0, '10km')
Query::nested('comments', Query::term('user', 'john'))
Query::exists('author')
Query::prefix('name', 'lar')
Query::wildcard('name', 'lar*')
Query::regexp('name', 'lar.*')
Query::fuzzy('name', 'larvel')
Query::ids(['1', '2', '3'])
Query::matchPhrase('title', 'laravel framework')
Query::matchPhrasePrefix('title', 'laravel fra')
```

### Bool Query Methods

**OLD**: Single query per call (`must()`, `should()`, etc.)
**NEW**: Renamed to `addMust()`, `addShould()`, etc. Variadic support via `addMustMany()`, etc.

```php
// OLD
Query::bool()
    ->must(Query::match()->field('title')->query('laravel'))
    ->must(Query::match()->field('body')->query('php'))
    ->filter(Query::term()->field('status')->value('active'))

// NEW - option 1: individual calls
Query::bool()
    ->addMust(Query::match('title', 'laravel'))
    ->addMust(Query::match('body', 'php'))
    ->addFilter(Query::term('status', 'active'))

// NEW - option 2: variadic
Query::bool()
    ->addMustMany(
        Query::match('title', 'laravel'),
        Query::match('body', 'php')
    )
    ->addFilter(Query::term('status', 'active'))
```

| OLD | NEW |
|-----|-----|
| `must($query)` | `addMust($query)` |
| `mustNot($query)` | `addMustNot($query)` |
| `should($query)` | `addShould($query)` |
| `filter($query)` | `addFilter($query)` |
| `mustRaw(array)` | `addMustRaw(array)` (not available, use raw arrays) |

### Soft Delete Handling

```php
// OLD - on BoolQuery
Query::bool()->withTrashed()
Query::bool()->onlyTrashed()

// NEW - on BoolQuery (same methods, but enum-based internally)
Query::bool()->withTrashed()
Query::bool()->onlyTrashed()
Query::bool()->excludeTrashed()  // NEW: explicit exclude
```

### Nested Query with Closure

```php
// OLD
Query::nested()->path('comments')->query(
    Query::bool()->must(Query::term()->field('comments.user')->value('john'))
)

// NEW - closure receives BoolQuery
Query::nested('comments', fn(BoolQuery $q) => $q
    ->addMust(Query::term('comments.user', 'john'))
)

// NEW - or pass query directly
Query::nested('comments', Query::term('comments.user', 'john'))
```

---

## SearchBuilder API Changes

### Method Renames

| OLD | NEW |
|-----|-----|
| `load(array $relations)` | `with(array $relations)` |
| `setEloquentQueryCallback(Closure)` | `modifyQuery(Closure)` |
| `setEloquentCollectionCallback(Closure)` | `modifyModels(Closure)` |
| `rescoreQuery($query)` | `rescore($query, ...)` (combined) |
| `rescoreWindowSize(int)` | `rescore(..., windowSize: int)` |
| `rescoreWeights(float, float)` | `rescore(..., queryWeight: float, rescoreQueryWeight: float)` |

### Bool Query Shortcuts on SearchBuilder

**NEW**: SearchBuilder has direct bool clause methods:

```php
// OLD
Book::searchQuery(
    Query::bool()
        ->must(Query::match()->field('title')->query('laravel'))
        ->filter(Query::term()->field('status')->value('published'))
)->execute();

// NEW (shorter) - bool clauses directly on builder
Book::searchQuery()
    ->must(Query::match('title', 'laravel'))
    ->filter(Query::term('status', 'published'))
    ->execute();

// You can still pass explicit query
Book::searchQuery(Query::matchAll())
    ->filter(Query::term('status', 'published'))
    ->execute();
```

### Rescore (Combined API)

```php
// OLD - separate methods
->rescoreQuery(Query::match()->field('title')->query('exact'))
->rescoreWindowSize(100)
->rescoreWeights(0.7, 1.2)

// NEW - single method with named parameters
->rescore(
    Query::match('title', 'exact'),
    windowSize: 100,
    queryWeight: 0.7,
    rescoreQueryWeight: 1.2
)
```

### Eager Loading

```php
// OLD
->load(['author', 'publisher'])
->load(['tags'], Book::class)  // for multi-index

// NEW
->with(['author', 'publisher'])
->with(['tags'], Book::class)
```

### Query/Collection Callbacks

```php
// OLD
->setEloquentQueryCallback(fn($query) => $query->where('active', true))
->setEloquentCollectionCallback(fn($models) => $models->load('comments'))

// NEW
->modifyQuery(fn($query) => $query->where('active', true))
->modifyModels(fn($models) => $models->load('comments'))
```

### Source Filtering

```php
// OLD - only includes
->source(['title', 'body'])

// NEW - includes and excludes
->source(['title', 'body'])                    // same
->source(['*'], excludes: ['large_field'])     // NEW: excludes support
->withoutSource()                              // NEW: disable source
```

### Routing

```php
// OLD - array only
->routing(['user_123'])

// NEW - string, int, or array
->routing('user_123')
->routing(123)
->routing(['user_123', 'user_456'])
```

### Highlight

```php
// OLD
->highlight('title', ['fragment_size' => 100])

// NEW - same, plus named parameters
->highlight('title', fragmentSize: 100, numberOfFragments: 3)
->highlightGlobal(preTags: ['<em>'], postTags: ['</em>'])  // NEW
```

---

## SearchResult API Changes

### Properties vs Methods

```php
// OLD (methods via __call forwarding)
$result->total()
$result->took()
$result->timedOut()

// NEW (direct properties + raw access)
$result->total          // property
$result->maxScore       // property
$result->raw['took']    // via raw array
$result->raw['timed_out']
```

### Aggregation Access

```php
// OLD
$result->aggregations()                    // Collection
$result->aggregations()->get('avg_price')

// NEW
$result->aggregations()                    // array
$result->aggregation('avg_price')          // single agg
$result->aggregationValue('avg_price')     // shortcut for ->value
$result->buckets('by_category')            // shortcut for bucket aggs
```

---

## Hit API Changes

### Properties vs Methods

```php
// OLD (methods via __call)
$hit->indexName()
$hit->document()->id()
$hit->document()->content()
$hit->score()
$hit->highlight()->raw()

// NEW (direct properties)
$hit->indexName
$hit->documentId
$hit->source         // was document()->content()
$hit->score
$hit->highlight      // array, not object
$hit->sort           // NEW: sort values
$hit->explanation    // NEW: explain output
$hit->raw            // NEW: full raw hit
```

---

## Paginator API Changes

```php
// OLD - mutates in place
$paginator->onlyModels()
$paginator->onlyDocuments()

// NEW - returns clone
$paginator->withModels()
$paginator->withDocuments()
$paginator->searchResult()   // NEW: access underlying SearchResult
```

---

## Aggregation API

**NEW**: Dedicated `Agg` factory (not available in elastic-scout-driver-plus)

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;

// OLD (raw arrays only)
->aggregateRaw([
    'avg_price' => ['avg' => ['field' => 'price']],
    'by_category' => ['terms' => ['field' => 'category', 'size' => 10]]
])

// NEW (type-safe builders)
->aggregate('avg_price', Agg::avg('price'))
->aggregate('by_category', Agg::terms('category')->size(10))

// Sub-aggregations
->aggregate('by_category',
    Agg::terms('category')
        ->size(10)
        ->subAggregation('avg_price', Agg::avg('price'))
)
```

### Available Aggregations

| Category | Types |
|----------|-------|
| **Metric** | `avg`, `sum`, `min`, `max`, `stats`, `extendedStats`, `cardinality`, `percentiles`, `topHits`, `geoBounds`, `geoCentroid` |
| **Bucket** | `terms`, `histogram`, `dateHistogram`, `range`, `filter`, `filters`, `global`, `nested`, `reverseNested`, `geoDistance`, `composite` |

---

## Sort API

**NEW**: Dedicated `Sort` factory

```php
use Jackardios\EsScoutDriver\Sort\Sort;

// OLD
->sort('created_at', 'desc')
->sortRaw([['_score' => 'desc'], ['created_at' => 'asc']])

// NEW - both styles work
->sort('created_at', 'desc')
->sort('created_at', 'desc', missing: '_last', mode: 'avg')  // NEW: params

// NEW - typed sort objects
->sort(Sort::field('created_at')->order('desc')->missing('_last'))
->sort(Sort::score())
->sort(Sort::geoDistance('location', 40.7, -74.0)->unit('km')->order('asc'))
->sort(Sort::script(['source' => 'doc["priority"].value'], 'number'))
```

---

## New Features in es-scout-driver

### Additional Query Types

Not available in elastic-scout-driver-plus:

```php
// Full-text
Query::combinedFields(['title', 'body'], 'search')  // ES 7.13+
Query::queryString('title:laravel AND status:published')
Query::simpleQueryString('laravel | php')

// Compound
Query::functionScore(Query::matchAll())->addScriptScore([...])
Query::disMax([Query::match('title', 'a'), Query::match('body', 'a')])
Query::boosting(positive: Query::match('title', 'a'), negative: Query::term('status', 'draft'))
Query::constantScore(Query::term('status', 'active'))

// Specialized
Query::scriptScore(Query::matchAll(), ['source' => '_score * doc["boost"].value'])
Query::moreLikeThis(['title'], 'Sample text to match')
Query::knn('vector_field', [0.1, 0.2, ...], k: 10)
Query::sparseVector('ml_field')->inferenceId('model-id')
Query::semantic('semantic_field', 'query text')  // ES 8.14+
Query::textExpansion('ml_field', 'model-id')     // ES 8.8+
Query::pinned(organic: Query::matchAll())->ids(['1', '2'])

// Geo
Query::geoBoundingBox('location', 40.73, -74.1, 40.01, -71.12)
Query::geoShape('location')->indexedShape('index', 'doc_id')

// Joining
Query::hasChild('answer', Query::match('body', 'elasticsearch'))
Query::hasParent('question', Query::match('title', 'search'))
Query::parentId('answer', 'parent_doc_id')

// Raw
Query::raw(['custom' => ['query' => 'structure']])
```

### Top-Level KNN (ES 8.12+)

```php
Book::searchQuery()
    ->knn('embedding', $queryVector, k: 10, numCandidates: 100)
    ->filter(Query::term('status', 'published'))  // combine with filters
    ->execute();
```

### Cursor-Based Pagination

```php
// Chunk processing (memory efficient)
Book::searchQuery(Query::matchAll())
    ->sort('_id')
    ->chunk(1000, function (array $hits) {
        foreach ($hits as $hit) {
            // process
        }
        return true;  // return false to stop
    });

// Manual cursor iteration
foreach (Book::searchQuery(Query::matchAll())->cursor(1000) as $hit) {
    // process each hit
}
```

### Convenience Methods

```php
// First hit
$hit = Book::searchQuery(Query::match('title', 'laravel'))->first();
$hit = Book::searchQuery(Query::match('title', 'laravel'))->firstOrFail();

// Count
$count = Book::searchQuery(Query::term('status', 'published'))->count();

// Bulk operations
Book::searchQuery(Query::term('status', 'draft'))
    ->deleteByQuery();

Book::searchQuery(Query::term('status', 'draft'))
    ->updateByQuery(['source' => 'ctx._source.status = "archived"']);

// Debug
echo $builder->toJson();  // pretty-printed JSON
$params = $builder->toArray();
```

### Additional SearchBuilder Options

```php
->timeout('5s')
->storedFields(['title', 'body'])
->docvalueFields(['created_at'])
->version(true)
->scriptFields(['custom_field' => ['script' => '...']])
->runtimeMappings(['day_of_week' => ['type' => 'keyword', 'script' => '...']])
```

### Clear Methods

Every setter has a corresponding clear method:

```php
->clearQuery()
->clearBoolQuery()
->clearHighlight()
->clearSort()
->clearRescore()
->clearSuggest()
->clearSource()
->clearCollapse()
->clearAggregations()
->clearPostFilter()
->clearPointInTime()
->clearSearchAfter()
->clearRouting()
->clearKnn()
->clearIndicesBoost()
->clearQueryModifiers()
->clearModelModifiers()
->clearAll()  // clears everything
```

---

## Configuration

### New Config Files

**config/elastic.scout.php**:
```php
return [
    'refresh_documents' => false,
    'model_hydration_mismatch' => 'ignore', // 'ignore', 'log', 'exception'
    'bulk_failure_mode' => 'exception',     // 'exception', 'log', 'ignore'
];
```

**config/elastic.client.php**:
```php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'hosts' => [env('ELASTICSEARCH_HOST', 'http://localhost:9200')],
        ],
    ],
];
```

---

## Quick Reference: Common Migrations

```php
// 1. Simple match
// OLD
Book::searchQuery(Query::match()->field('title')->query('laravel'))->execute();
// NEW
Book::searchQuery(Query::match('title', 'laravel'))->execute();

// 2. Bool with filters
// OLD
Book::searchQuery(
    Query::bool()
        ->must(Query::match()->field('title')->query('laravel'))
        ->filter(Query::term()->field('status')->value('published'))
        ->filter(Query::range()->field('price')->lte(100))
)->execute();
// NEW
Book::searchQuery()
    ->must(Query::match('title', 'laravel'))
    ->filter(Query::term('status', 'published'), Query::range('price')->lte(100))
    ->execute();

// 3. Aggregations
// OLD
Book::searchQuery(Query::matchAll())
    ->aggregateRaw(['avg_price' => ['avg' => ['field' => 'price']]])
    ->execute();
// NEW
Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->execute();

// 4. Highlighting and pagination
// OLD
Book::searchQuery(Query::match()->field('title')->query('laravel'))
    ->highlight('title')
    ->load(['author'])
    ->paginate(20);
// NEW
Book::searchQuery(Query::match('title', 'laravel'))
    ->highlight('title')
    ->with(['author'])
    ->paginate(20);

// 5. Nested query
// OLD
Product::searchQuery(
    Query::nested()
        ->path('variants')
        ->query(Query::bool()
            ->must(Query::term()->field('variants.color')->value('red'))
            ->must(Query::range()->field('variants.price')->lte(50))
        )
)->execute();
// NEW
Product::searchQuery(
    Query::nested('variants', fn(BoolQuery $q) => $q
        ->addMust(Query::term('variants.color', 'red'))
        ->addMust(Query::range('variants.price')->lte(50))
    )
)->execute();

// 6. Accessing results
// OLD
$result = Book::searchQuery(...)->execute();
$total = $result->total();
foreach ($result->hits() as $hit) {
    $id = $hit->document()->id();
    $title = $hit->document()->content()['title'];
    $model = $hit->model();
}
// NEW
$result = Book::searchQuery(...)->execute();
$total = $result->total;
foreach ($result->hits() as $hit) {
    $id = $hit->documentId;
    $title = $hit->source['title'];
    $model = $hit->model();
}

// 7. Paginator
// OLD
$paginator = Book::searchQuery(...)->paginate(20);
$modelsOnly = $paginator->onlyModels();
// NEW
$paginator = Book::searchQuery(...)->paginate(20);
$modelsOnly = $paginator->withModels();
```

---

## Regex Find & Replace Patterns

For bulk migration:

```regex
# Namespace
Elastic\\ScoutDriverPlus → Jackardios\EsScoutDriver

# Query factory - basic types
Query::match\(\)->field\('([^']+)'\)->query\(([^)]+)\) → Query::match('$1', $2)
Query::term\(\)->field\('([^']+)'\)->value\(([^)]+)\) → Query::term('$1', $2)
Query::terms\(\)->field\('([^']+)'\)->values\(([^)]+)\) → Query::terms('$1', $2)
Query::range\(\)->field\('([^']+)'\) → Query::range('$1')
Query::exists\(\)->field\('([^']+)'\) → Query::exists('$1')
Query::prefix\(\)->field\('([^']+)'\)->value\(([^)]+)\) → Query::prefix('$1', $2)
Query::wildcard\(\)->field\('([^']+)'\)->value\(([^)]+)\) → Query::wildcard('$1', $2)
Query::regexp\(\)->field\('([^']+)'\)->value\(([^)]+)\) → Query::regexp('$1', $2)
Query::fuzzy\(\)->field\('([^']+)'\)->value\(([^)]+)\) → Query::fuzzy('$1', $2)
Query::ids\(\)->values\( → Query::ids(
Query::matchPhrase\(\)->field\('([^']+)'\)->query\(([^)]+)\) → Query::matchPhrase('$1', $2)
Query::matchPhrasePrefix\(\)->field\('([^']+)'\)->query\(([^)]+)\) → Query::matchPhrasePrefix('$1', $2)
Query::nested\(\)->path\('([^']+)'\)->query\( → Query::nested('$1',

# BoolQuery methods
->must\( → ->addMust(
->mustNot\( → ->addMustNot(
->should\( → ->addShould(
->filter\( → ->addFilter(

# SearchBuilder methods
->load\( → ->with(
->setEloquentQueryCallback\( → ->modifyQuery(
->setEloquentCollectionCallback\( → ->modifyModels(

# Paginator
->onlyModels\(\) → ->withModels()
->onlyDocuments\(\) → ->withDocuments()

# Hit properties
->document\(\)->id\(\) → ->documentId
->document\(\)->content\(\) → ->source
->indexName\(\) → ->indexName
->score\(\) → ->score
->highlight\(\)->raw\(\) → ->highlight

# SearchResult
->total\(\) → ->total
```

---

## Query Method Differences

Some query methods have different availability:

| Query Type | Method | OLD | NEW |
|------------|--------|-----|-----|
| `MultiMatchQuery` | `slop()` | Available | Not available (use `MatchPhraseQuery` instead) |
| `MultiMatchQuery` | `type()` | `type('best_fields')` | `type(MultiMatchType::BestFields)` or `type('best_fields')` |
| All queries | Build method | `buildQuery()` | `toArray()` |

### Interface Change

```php
// OLD - QueryBuilderInterface
interface QueryBuilderInterface {
    public function buildQuery(): array;
}

// NEW - QueryInterface
interface QueryInterface {
    public function toArray(): array;
}
```

---

## Breaking Changes Summary

| Area | Change |
|------|--------|
| **Namespace** | `Elastic\ScoutDriverPlus` → `Jackardios\EsScoutDriver` |
| **Query construction** | `Query::match()->field()->query()` → `Query::match(field, query)` |
| **BoolQuery methods** | `must()` → `addMust()` |
| **SearchBuilder** | `load()` → `with()`, `setEloquentQueryCallback()` → `modifyQuery()` |
| **Rescore** | 3 methods → 1 combined method |
| **Hit access** | Methods (`->document()->id()`) → Properties (`->documentId`) |
| **SearchResult.total** | Method → Property |
| **Paginator** | `onlyModels()` (mutates) → `withModels()` (clones) |
| **Config** | New config files required |
