# Search Builder

SearchBuilder is the main API for executing searches in Elasticsearch. It provides a fluent interface for building complex queries.

## Creating a Search Builder

```php
use Jackardios\EsScoutDriver\Support\Query;

// Empty search (matches all documents)
$builder = Book::searchQuery();

// With initial query
$builder = Book::searchQuery(Query::match('title', 'laravel'));

// With array query
$builder = Book::searchQuery(['match' => ['title' => 'laravel']]);

// With closure
$builder = Book::searchQuery(fn() => Query::match('title', 'laravel'));
```

## Bool Query Methods

SearchBuilder provides convenient shortcuts for building bool queries:

```php
Book::searchQuery()
    ->must(Query::match('title', 'laravel'))
    ->must(Query::match('author', 'john'))
    ->should(Query::term('featured', true))
    ->filter(Query::range('price')->lte(100))
    ->mustNot(Query::term('status', 'draft'))
    ->execute();
```

### must()

Adds clauses that must match. Contributes to the score.

```php
$builder->must(Query::match('title', 'laravel'));
$builder->must(Query::match('title', 'laravel'), Query::match('author', 'john')); // Multiple
```

### should()

Adds clauses that should match. Increases the score if matched.

```php
$builder->should(Query::term('featured', true));
$builder->should(Query::match('title', 'php'), Query::match('title', 'laravel'));
```

### filter()

Adds filter clauses. Must match, but doesn't affect scoring.

```php
$builder->filter(Query::term('status', 'published'));
$builder->filter(Query::range('price')->gte(10)->lte(100));
```

### mustNot()

Adds clauses that must not match.

```php
$builder->mustNot(Query::term('status', 'draft'));
```

## Pagination

### size() and from()

```php
$builder->from(20)->size(10)->execute(); // Skip 20, take 10
```

### paginate()

Returns a Laravel-compatible paginator:

```php
$paginator = Book::searchQuery(Query::matchAll())
    ->paginate(perPage: 15, pageName: 'page', page: 1);

// In Blade
@foreach ($paginator->models() as $book)
    {{ $book->title }}
@endforeach

{{ $paginator->links() }}
```

> `perPage` must be greater than `0`, and `page` must be greater than or equal to `1`.
> `paginate()` automatically resets any previously set `searchAfter()` cursor state.

### cursor()

For efficient iteration over large result sets using Point-in-Time:

```php
$cursor = Book::searchQuery(Query::matchAll())
    ->sort('_id')
    ->cursor(chunkSize: 1000, keepAlive: '5m');

foreach ($cursor as $hit) {
    echo $hit->source['title'];
}
```

> `chunkSize` must be greater than `0`.

### chunk()

Process results in chunks:

```php
Book::searchQuery(Query::matchAll())
    ->sort('_id')
    ->chunk(1000, function (array $hits) {
        foreach ($hits as $hit) {
            // Process hit
        }
    });
```

> `chunkSize` must be greater than `0`.

## Sorting

### Simple sort

```php
$builder->sort('created_at', 'desc');
$builder->sort('price', 'asc');
```

### With options

```php
$builder->sort('price', 'desc', missing: '_last', mode: 'avg');
```

### Using Sort objects

```php
use Jackardios\EsScoutDriver\Sort\Sort;

$builder
    ->sort(Sort::field('price')->desc()->missingLast())
    ->sort(Sort::score())
    ->sort(Sort::geoDistance('location', 52.3676, 4.9041)->asc());
```

### Raw sort

```php
$builder->sortRaw([
    ['price' => 'desc'],
    ['_score' => 'desc'],
]);
```

## Highlighting

### Basic highlighting

```php
$builder->highlight('title');
$builder->highlight('description', fragmentSize: 100, numberOfFragments: 3);
```

### With tags

```php
$builder->highlight('title', preTags: ['<mark>'], postTags: ['</mark>']);
```

### Global settings

```php
$builder
    ->highlightGlobal(
        fragmentSize: 150,
        preTags: ['<em>'],
        postTags: ['</em>'],
        type: 'unified'
    )
    ->highlight('title')
    ->highlight('description');
```

### Raw highlight

```php
$builder->highlightRaw([
    'fields' => [
        'title' => ['number_of_fragments' => 0],
        'description' => ['fragment_size' => 200],
    ],
    'pre_tags' => ['<mark>'],
    'post_tags' => ['</mark>'],
]);
```

## Source Filtering

### Include specific fields

```php
$builder->source(['title', 'author', 'price']);
```

### Include and exclude

```php
$builder->source(['title', 'author.*'], excludes: ['author.email']);
```

### Disable source

```php
$builder->withoutSource();
// or
$builder->sourceRaw(false);
```

> **Tip:** Use `withoutSource()` when you only need models from the database and don't need document data. This reduces Elasticsearch response size since `_source` contents are not transferred.

```php
// Optimized: only IDs are fetched from ES, models loaded from DB
$models = Book::searchQuery(Query::match('title', 'laravel'))
    ->withoutSource()
    ->execute()
    ->models();

// Optimized pagination
$paginator = Book::searchQuery(Query::matchAll())
    ->withoutSource()
    ->paginate()
    ->withModels();
```

## Aggregations

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;

$result = Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->aggregate('price_ranges', Agg::range('price')
        ->range(to: 20)
        ->range(from: 20, to: 50)
        ->range(from: 50)
    )
    ->aggregate('by_author', Agg::terms('author')
        ->size(10)
        ->agg('total_sales', Agg::sum('sales'))
    )
    ->size(0) // Only get aggregations, no hits
    ->execute();

$avgPrice = $result->aggregationValue('avg_price');
$authorBuckets = $result->buckets('by_author');
```

## Suggestions

```php
$result = Book::searchQuery(Query::match('title', 'laravel'))
    ->suggest('title_suggest', [
        'text' => 'laravel',
        'term' => ['field' => 'title'],
    ])
    ->execute();

$suggestions = $result->suggestions();
```

## Collapse

Collapse results by a field (field collapsing / de-duplication):

```php
$builder->collapse('author_id');

// With inner hits
$builder->collapseRaw([
    'field' => 'author_id',
    'inner_hits' => [
        'name' => 'by_author',
        'size' => 3,
    ],
]);
```

## Post Filter

Filter results after aggregations are calculated:

```php
$builder
    ->aggregate('colors', Agg::terms('color'))
    ->postFilter(Query::term('color', 'red'));
```

## Rescore

Re-score top results with a more expensive query:

```php
$builder->rescore(
    Query::matchPhrase('title', 'complete guide'),
    windowSize: 100,
    queryWeight: 0.7,
    rescoreQueryWeight: 1.2
);
```

## KNN (Vector Search)

```php
$builder->knn(
    field: 'embedding',
    queryVector: [0.12, -0.34, 0.56, 0.78],
    k: 10,
    numCandidates: 100,
    filter: Query::term('status', 'published')
);
```

## Multi-Index Search

```php
Book::searchQuery(Query::match('title', 'laravel'))
    ->join(Article::class)
    ->join(Post::class, boost: 1.5) // Boost this index
    ->execute();
```

> **Important:** All joined models must use the same `searchableConnection()`.  
> Cross-connection joins are not supported because a single Elasticsearch request cannot span multiple client connections.
>
> **Important:** A joined index must map to exactly one model class.  
> Joining two different model classes that return the same `searchableAs()` value is rejected to avoid ambiguous model resolution.

## Eager Loading

```php
$builder->with(['author', 'categories']);

// For specific model in multi-index search
$builder
    ->join(Article::class)
    ->with(['author'], Book::class)
    ->with(['tags'], Article::class);
```

> In multi-index mode (after at least one `join()`), pass `modelClass` explicitly to `with()`.

## Query Modifiers

### modifyQuery()

Modify the Eloquent query before loading models:

```php
$builder->modifyQuery(function ($query, $rawResult) {
    $query->withCount('reviews');
});
```

> **Warning:** Do not use `modifyQuery()` to filter results (e.g., `->where('active', true)`) unless you intentionally accept hydration mismatch (`hits` count in Elasticsearch can differ from hydrated models count). For strict safety, set `elastic.scout.model_hydration_mismatch=exception`.
>
> In multi-index mode (after at least one `join()`), pass `modelClass` explicitly to `modifyQuery()`.

### modifyModels()

Modify the loaded model collection:

```php
$builder->modifyModels(function ($models) {
    return $models->each(fn($m) => $m->computed_field = $m->calculateSomething());
});
```

> **Warning:** Do not use `modifyModels()` to filter results unless you intentionally accept hydration mismatch (`hits` count in Elasticsearch can differ from hydrated models count). For strict safety, set `elastic.scout.model_hydration_mismatch=exception`.
>
> In multi-index mode (after at least one `join()`), pass `modelClass` explicitly to `modifyModels()`.

## Soft Deletes

When `scout.soft_delete` is enabled:

```php
// Exclude trashed (default)
Book::searchQuery(Query::matchAll())->execute();

// Include trashed
$builder = Book::searchQuery(Query::matchAll());
$builder->boolQuery()->withTrashed();
$builder->execute();

// Only trashed
$builder = Book::searchQuery(Query::matchAll());
$builder->boolQuery()->onlyTrashed();
$builder->execute();
```

## Execution Methods

### execute()

Returns a `SearchResult` object:

```php
$result = $builder->execute();
$result->hits();    // Collection of Hit objects
$result->models();  // Eloquent Collection
$result->total;     // Total count
```

### raw()

Returns the raw Elasticsearch response:

```php
$rawResponse = $builder->raw();
```

### first()

Get the first hit:

```php
$hit = $builder->first();
$model = $hit?->model();
```

### firstOrFail()

Get the first hit or throw exception:

```php
$hit = $builder->firstOrFail();
```

### count()

Get total count without loading results:

```php
$count = $builder->count();
```

### deleteByQuery()

Delete documents matching the query:

```php
Book::searchQuery(Query::term('status', 'draft'))
    ->deleteByQuery();
```

> `deleteByQuery()` requires an explicit query. Use `Query::matchAll()` to target all visible documents.
> When `scout.soft_delete=true`, set mode before execution: `$builder->boolQuery()->withTrashed();`.

### updateByQuery()

Update documents matching the query:

```php
Book::searchQuery(Query::term('status', 'draft'))
    ->updateByQuery([
        'source' => "ctx._source.status = 'archived'",
        'lang' => 'painless',
    ]);
```

> `updateByQuery()` requires an explicit query. Use `Query::matchAll()` to target all visible documents.
> When `scout.soft_delete=true`, set mode before execution: `$builder->boolQuery()->withTrashed();`.

## Debugging

### toJson()

```php
echo $builder->toJson(JSON_PRETTY_PRINT);
```

### toArray()

```php
$params = $builder->toArray();
```

## Additional Options

```php
$builder
    ->trackTotalHits(true)       // Track exact total count
    ->trackScores(true)          // Track scores when sorting
    ->minScore(0.5)              // Minimum score threshold
    ->explain(true)              // Include score explanation
    ->timeout('5s')              // Query timeout
    ->preference('_local')       // Shard preference
    ->routing('user_1')          // Custom routing
    ->requestCache(true)         // Enable request cache
    ->terminateAfter(1000)       // Max documents to collect
    ->version(true);             // Include version in hits
```

## Point-in-Time

For consistent pagination across multiple requests:

```php
// Open PIT
$pitId = Book::openPointInTime('5m');

// Use PIT
$result = Book::searchQuery(Query::matchAll())
    ->pointInTime($pitId)
    ->sort('_id')
    ->size(100)
    ->execute();

// For next page
$lastHit = $result->hits()->last();
$nextResult = Book::searchQuery(Query::matchAll())
    ->pointInTime($pitId)
    ->searchAfter($lastHit->sort)
    ->from(0) // search_after is only valid with from=0
    ->sort('_id')
    ->size(100)
    ->execute();

// Close PIT when done
Book::closePointInTime($pitId);
```
