# Search Results

This document covers working with search results.

## Table of Contents

- [SearchResult](#searchresult)
- [Hit](#hit)
- [Paginator](#paginator)
- [Cursor](#cursor)

---

## SearchResult

The `execute()` method returns a `SearchResult` object:

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Support\Query;

$result = Book::searchQuery(Query::match('title', 'laravel'))->execute();
```

### Properties

```php
$result->total;      // int - Total number of matching documents
$result->totalRelation; // ?string - "eq" for an exact total, "gte" for a lower bound, null when not tracked
$result->maxScore;   // ?float - Highest relevance score
$result->raw;        // array - Raw Elasticsearch response
```

### Partial Shard Failures

When some shards fail, Elasticsearch still answers with the hits of the other shards. The driver does not throw or
log in that case; check the `_shards` section of the raw response when a partial result matters:

```php
if (($result->raw['_shards']['failed'] ?? 0) > 0) {
    $failures = $result->raw['_shards']['failures'] ?? [];
}

$result->raw['timed_out']; // true when timeout() cut the search short
```

### Getting Hits

```php
// Collection of Hit objects
$hits = $result->hits();

foreach ($hits as $hit) {
    echo $hit->documentId;
    echo $hit->score;
    print_r($hit->source);
}
```

### Getting Models

```php
// Eloquent Collection of models
$models = $result->models();

foreach ($models as $book) {
    echo $book->title;
}
```

Note: Models are lazy-loaded from the database when first accessed.

The models are loaded with one `whereIn` on the model's `getScoutKeyName()` column per index, shaped by `with()`,
`modifyQuery()` and `modifyModels()`. Scout's `queryScoutModelsByIds()` and `getScoutModelsByIds()` are not called
here (they are for `Model::search()`), so a model that overrides them, for example for a composite `getScoutKey()`,
must do the same in `modifyQuery()`.

> **Tip:** If you only need models and don't need document data, use `withoutSource()` to disable `_source` fetching and reduce network traffic:
> ```php
> $models = Book::searchQuery(...)->withoutSource()->execute()->models();
> ```

### Getting Documents

```php
// Collection of source documents (arrays)
$documents = $result->documents();

foreach ($documents as $doc) {
    echo $doc['title'];
}
```

### Highlights

```php
$result = Book::searchQuery(Query::match('title', 'laravel'))
    ->highlight('title')
    ->highlight('description')
    ->execute();

// Collection of highlight arrays
$highlights = $result->highlights();

foreach ($highlights as $highlight) {
    // ['title' => ['<em>Laravel</em> Guide'], 'description' => [...]]
    print_r($highlight);
}

// Or access via hits
foreach ($result->hits() as $hit) {
    print_r($hit->highlight);
}
```

### Suggestions

```php
$result = Book::searchQuery(Query::match('title', 'laravl'))
    ->suggest('title_suggest', [
        'text' => 'laravl',
        'term' => ['field' => 'title'],
    ])
    ->execute();

// Collection keyed by suggestion name
$suggestions = $result->suggestions();

foreach ($suggestions['title_suggest'] as $suggestion) {
    echo "Text: {$suggestion->text}\n";
    echo "Offset: {$suggestion->offset}\n";
    echo "Length: {$suggestion->length}\n";

    foreach ($suggestion->options as $option) {
        $score = $option['_score'] ?? $option['score'] ?? null;
        echo "  - {$option['text']} (score: {$score})\n";
    }
}
```

### Aggregations

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->aggregate('by_category', Agg::terms('category'))
    ->execute();

// All aggregations
$aggs = $result->aggregations();

// Specific aggregation
$avgPriceAgg = $result->aggregation('avg_price');
// ['value' => 45.50]

// Single value
$avgPrice = $result->aggregationValue('avg_price');
// 45.50

// With custom key
$count = $result->aggregationValue('stats_agg', 'count');

// Buckets
$buckets = $result->buckets('by_category');
// Collection of ['key' => 'Fiction', 'doc_count' => 42, ...]
// Keyed by bucket name for keyed range aggregations and named filters aggregations

// The after_key of a composite aggregation, for the next page
$afterKey = $result->aggregationValue('by_author', 'after_key');
```

### Iteration

SearchResult is iterable:

```php
foreach ($result as $hit) {
    echo $hit->documentId;
}
```

---

## Hit

Each search result hit is represented by a `Hit` object.

### Properties

```php
$hit->indexName;    // string - The index this hit came from
$hit->documentId;   // string - Document ID
$hit->score;        // ?float - Relevance score
$hit->source;       // array - Document source data
$hit->highlight;    // array - Highlighted fragments
$hit->sort;         // array - Sort values (for search_after)
$hit->explanation;  // array - Score explanation (if explain=true)
$hit->raw;          // array - Raw hit from Elasticsearch
```

### Getting the Model

```php
$model = $hit->model();

if ($model !== null) {
    echo $model->title;
}
```

Note: Returns `null` if the model doesn't exist in the database.

### Inner Hits

For nested or parent/child queries:

```php
$result = Book::searchQuery(
    Query::nested('reviews', Query::match('reviews.text', 'excellent'))
        ->innerHits(['size' => 5])
)->execute();

foreach ($result->hits() as $hit) {
    // Collection keyed by inner_hits name
    $innerHits = $hit->innerHits();

    foreach ($innerHits['reviews'] as $reviewHit) {
        echo $reviewHit->source['text'];
    }
}
```

### Converting to Array

```php
$array = $hit->toArray();
// [
//     'index_name' => 'books',
//     'document_id' => '123',
//     'score' => 1.5,
//     'source' => [...],
//     'highlight' => [...],
//     'sort' => [...],
//     'explanation' => [...],
// ]
```

---

## Paginator

The `paginate()` method returns a Laravel-compatible paginator:

```php
$paginator = Book::searchQuery(Query::matchAll())
    ->sort('created_at', 'desc')
    ->paginate(perPage: 15, pageName: 'page', page: 1);
```

`perPage` must be greater than `0`, and `page` must be greater than or equal to `1`.

The total is exact unless the builder sets `trackTotalHits()`. With `trackTotalHits(int)` Elasticsearch stops counting
at that number: `total()`, `lastPage()` and `hasMorePages()` then use a lower bound, and
`$paginator->searchResult()->totalRelation` is `gte`.

### Basic Usage

```php
// Total results
$paginator->total();

// Current page
$paginator->currentPage();

// Items per page
$paginator->perPage();

// Last page number
$paginator->lastPage();

// Has more pages?
$paginator->hasMorePages();

// Items on current page
$paginator->items(); // Array of Hit objects
```

### Getting Models

```php
$models = $paginator->models();

foreach ($models as $book) {
    echo $book->title;
}
```

### In Blade Templates

```php
@foreach ($paginator->models() as $book)
    <div>{{ $book->title }}</div>
@endforeach

{{ $paginator->links() }}
```

### With Query Parameters

```php
{{ $paginator->appends(['sort' => 'price'])->links() }}
```

### SearchResult Access

```php
// Get the underlying SearchResult
$searchResult = $paginator->searchResult();

// Access aggregations from paginated results
$aggs = $paginator->searchResult()->aggregations();
```

---

## Cursor

For efficient iteration over large result sets:

```php
$cursor = Book::searchQuery(Query::matchAll())
    ->cursor(chunkSize: 1000, keepAlive: '5m');

foreach ($cursor as $hit) {
    echo $hit->source['title'];

    // Get model
    $model = $hit->model();
}
```

### How it works

The cursor uses Point-in-Time (PIT) and `search_after` for efficient pagination:

1. Opens a Point-in-Time snapshot, with the builder's `routing()` and `preference()`
2. Fetches results in chunks using `search_after`
3. Automatically closes the PIT when done

### Requirements

- **Sort is optional** - The driver ends the sort with `_shard_doc` unless it already contains it, so the order is
  always deterministic; do not sort on `_id`, which Elasticsearch refuses by default
- **No rescore** - `rescore()` cannot be combined with a sort, so `cursor()` and `chunk()` throw `LogicException`

### Memory efficient processing

```php
// Process millions of documents without loading all into memory
$cursor = Book::searchQuery(Query::term('status', 'pending'))
    ->sort('created_at')
    ->cursor(chunkSize: 500);

foreach ($cursor as $hit) {
    // Process one document at a time
    processDocument($hit->source);
}
```

### Chunk callback

Alternative using `chunk()`:

```php
Book::searchQuery(Query::matchAll())
    ->chunk(1000, function (array $hits) {
        foreach ($hits as $hit) {
            // Process hit
        }

        // Return false to stop iteration
        return true;
    });
```

---

## Raw Response

Get the raw Elasticsearch response:

```php
$rawResponse = Book::searchQuery(Query::matchAll())->raw();

// Access any part of the response
$rawResponse['took'];
$rawResponse['timed_out'];
$rawResponse['_shards'];
$rawResponse['hits']['total']['value'];
$rawResponse['hits']['hits'];
```

---

## First Result

Get just the first hit:

```php
$hit = Book::searchQuery(Query::match('title', 'laravel'))->first();

if ($hit !== null) {
    $model = $hit->model();
}
```

Or throw an exception if not found:

```php
try {
    $hit = Book::searchQuery(Query::match('title', 'xyz'))->firstOrFail();
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    // No results found
}
```

---

## Count

Get total count without loading results:

```php
$count = Book::searchQuery(
    Query::term('status', 'published')
)->count();
```

This runs a search request with `size(0)` and `track_total_hits(true)` to keep counting semantics consistent with the builder options.
Aggregations, suggesters, highlight, sort, rescore and collapse are left out of that request: they do not change the total.
