# Aggregations

Aggregations allow you to group and extract statistics from your data. They are created using the `Agg` factory class.

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Support\Query;
```

## Table of Contents

- [Using Aggregations](#using-aggregations)
- [Metric Aggregations](#metric-aggregations) — avg, sum, min, max, stats, cardinality, percentiles, extendedStats, topHits, geoBounds, geoCentroid
- [Bucket Aggregations](#bucket-aggregations) — terms, histogram, dateHistogram, range, filter, filters, global, nested, reverseNested, geoDistance, composite
- [Sub-Aggregations](#sub-aggregations)
- [Accessing Results](#accessing-results)
- [Raw Aggregations](#raw-aggregations)
- [Extending Agg](#extending-agg)

---

## Using Aggregations

Add aggregations to your search:

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->aggregate('by_category', Agg::terms('category'))
    ->execute();
```

Get aggregation results:

```php
// Get all aggregations
$aggs = $result->aggregations();

// Get specific aggregation
$avgPrice = $result->aggregation('avg_price');

// Get single value
$value = $result->aggregationValue('avg_price'); // Returns the 'value' key

// Get buckets
$buckets = $result->buckets('by_category');
```

To get only aggregations without hits:

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('stats', Agg::stats('price'))
    ->size(0)
    ->execute();
```

---

## Metric Aggregations

Metric aggregations compute metrics over a set of documents.

### avg

Calculate the average of a numeric field:

```php
Agg::avg('price')
```

### sum

Calculate the sum of a numeric field:

```php
Agg::sum('quantity')
```

### min

Find the minimum value:

```php
Agg::min('price')
```

### max

Find the maximum value:

```php
Agg::max('price')
```

### stats

Get count, min, max, avg, and sum in one request:

```php
Agg::stats('price')
```

Result:
```php
[
    'count' => 100,
    'min' => 5.99,
    'max' => 299.99,
    'avg' => 45.50,
    'sum' => 4550.00,
]
```

### cardinality

Count unique values (approximate):

```php
Agg::cardinality('author_id')
```

### percentiles

Calculate percentile values:

```php
Agg::percentiles('response_time')

Agg::percentiles('response_time')
    ->percents([50, 75, 90, 95, 99])
    ->keyed(true)
    ->missing(0)
```

### extendedStats

Extended statistics (variance, std deviation, etc.):

```php
Agg::extendedStats('price')

Agg::extendedStats('price')
    ->sigma(2)              // Standard deviation bounds multiplier
    ->missing(0)
```

Result:
```php
[
    'count' => 100,
    'min' => 5.99,
    'max' => 299.99,
    'avg' => 45.50,
    'sum' => 4550.00,
    'sum_of_squares' => 500000.0,
    'variance' => 2500.0,
    'std_deviation' => 50.0,
    'std_deviation_bounds' => ['upper' => 145.50, 'lower' => -54.50],
]
```

### topHits

Get the top matching documents per bucket:

```php
Agg::topHits()

Agg::topHits()
    ->size(3)
    ->from(0)
    ->sort('created_at', 'desc')
    ->source(['title', 'author'])
    ->highlight(['fields' => ['title' => new \stdClass()]])
```

### geoBounds

Calculate bounding box for geo points:

```php
Agg::geoBounds('location')

Agg::geoBounds('location')
    ->wrapLongitude(true)   // Wrap longitude around the dateline
```

### geoCentroid

Calculate the centroid (center point) of geo points:

```php
Agg::geoCentroid('location')
```

---

## Bucket Aggregations

Bucket aggregations group documents into buckets.

### terms

Group by field values:

```php
// Basic
Agg::terms('category')

// With options
Agg::terms('category')
    ->size(20)                  // Number of buckets to return
    ->minDocCount(5)            // Minimum documents per bucket
    ->shardSize(100)            // Candidates per shard
    ->orderByCount('desc')      // Order by document count
    ->orderByKey('asc')         // Order alphabetically
    ->order('_count', 'asc')    // Custom order
    ->missing('Unknown')        // Value for missing field
    ->include(['Electronics', 'Books'])  // Include only these
    ->exclude(['Other'])        // Exclude these
    ->showTermDocCountError(true)
```

### histogram

Group by numeric intervals:

```php
// Basic
Agg::histogram('price', 10)     // Interval of 10

// With options
Agg::histogram('price', 10)
    ->minDocCount(1)
    ->extendedBounds(0, 100)    // Force bucket range
    ->hardBounds(0, 1000)       // Limit bucket range
    ->offset(5)                 // Bucket offset
    ->order('_key', 'desc')
    ->missing(0)
```

### dateHistogram

Group by date intervals:

```php
// Basic
Agg::dateHistogram('created_at', '1M')  // Monthly buckets

// Calendar intervals: minute, hour, day, week, month, quarter, year
Agg::dateHistogram('created_at', '1d')

// With options
Agg::dateHistogram('created_at', '1M')
    ->minDocCount(0)
    ->extendedBounds('2024-01-01', '2024-12-31')
    ->format('yyyy-MM-dd')
    ->timeZone('+03:00')
    ->offset('+6h')
    ->order('_key', 'desc')
    ->missing('2024-01-01')
```

### range

Group by custom ranges:

```php
// Basic
Agg::range('price')
    ->range(to: 20)
    ->range(from: 20, to: 50)
    ->range(from: 50, to: 100)
    ->range(from: 100)

// With keys
Agg::range('price')
    ->range(to: 20, key: 'cheap')
    ->range(from: 20, to: 50, key: 'medium')
    ->range(from: 50, key: 'expensive')
```

### filter

Single filter bucket for computing aggregations on filtered documents:

```php
use Jackardios\EsScoutDriver\Support\Query;

// Basic
Agg::filter(Query::term('status', 'published'))

// With sub-aggregations
Agg::filter(Query::term('status', 'published'))
    ->agg('avg_price', Agg::avg('price'))
    ->agg('max_price', Agg::max('price'))

// Using raw array
Agg::filter(['term' => ['status' => 'published']])
```

### filters

Multiple named filter buckets:

```php
// Fluent API
Agg::filters()
    ->filter('published', Query::term('status', 'published'))
    ->filter('draft', Query::term('status', 'draft'))
    ->filter('archived', Query::term('status', 'archived'))

// With "other" bucket for unmatched documents
Agg::filters()
    ->filter('electronics', Query::term('category', 'electronics'))
    ->filter('books', Query::term('category', 'books'))
    ->otherBucket()
    ->otherBucketKey('other_categories')

// With sub-aggregations
Agg::filters()
    ->filter('expensive', Query::range('price')->gte(100))
    ->filter('cheap', Query::range('price')->lt(100))
    ->agg('avg_rating', Agg::avg('rating'))
```

### global

Bypass the query scope to aggregate over all documents:

```php
// Compare query results with global stats
Agg::global()
    ->agg('all_avg_price', Agg::avg('price'))
```

### nested

Aggregate on nested documents:

```php
// Basic
Agg::nested('comments')
    ->agg('avg_rating', Agg::avg('comments.rating'))

// With terms
Agg::nested('reviews')
    ->agg('reviewers', Agg::terms('reviews.author'))
```

### reverseNested

Escape from nested context back to parent documents:

```php
// Inside a nested aggregation, get back to parent
Agg::nested('comments')
    ->agg('authors', Agg::terms('comments.author')
        ->agg('parent_categories', Agg::reverseNested()
            ->agg('categories', Agg::terms('category'))
        )
    )

// Navigate to a specific ancestor path
Agg::reverseNested()->path('parent_path')
```

### geoDistance

Group by distance ranges from a point:

```php
// Basic
Agg::geoDistance('location', 52.3760, 4.894)
    ->range(to: 5)
    ->range(from: 5, to: 10)
    ->range(from: 10)

// With keys and unit
Agg::geoDistance('location', 52.3760, 4.894)
    ->range(to: 5, key: 'walking')
    ->range(from: 5, to: 20, key: 'biking')
    ->range(from: 20, key: 'driving')
    ->unit('km')

// With options
Agg::geoDistance('location', 52.3760, 4.894)
    ->ranges([
        ['to' => 100, 'key' => 'nearby'],
        ['from' => 100, 'key' => 'far'],
    ])
    ->unit('km')
    ->distanceType('arc')   // arc (accurate) or plane (fast)
    ->keyed()               // Return as object instead of array
```

### composite

Paginate through all unique combinations of field values:

```php
// Basic
Agg::composite()
    ->termsSource('category', 'category')
    ->termsSource('author', 'author')
    ->size(100)

// With date histogram source
Agg::composite()
    ->termsSource('status', 'status')
    ->dateHistogramSource('month', 'created_at', '1M')
    ->size(50)

// Pagination with after key
Agg::composite()
    ->termsSource('category', 'category')
    ->after(['category' => 'electronics'])
```

---

## Sub-Aggregations

Nest aggregations within bucket aggregations:

```php
// Single level
Agg::terms('category')
    ->size(10)
    ->agg('avg_price', Agg::avg('price'))
    ->agg('max_price', Agg::max('price'))

// Multiple levels
Agg::terms('category')
    ->agg('by_author', Agg::terms('author')
        ->size(5)
        ->agg('total_sales', Agg::sum('sales'))
    )

// Date histogram with stats
Agg::dateHistogram('created_at', '1M')
    ->agg('price_stats', Agg::stats('price'))
    ->agg('by_status', Agg::terms('status'))
```

---

## Accessing Results

### Basic aggregation value

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->execute();

$avgPrice = $result->aggregationValue('avg_price');
// 45.50
```

### Stats aggregation

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('price_stats', Agg::stats('price'))
    ->execute();

$stats = $result->aggregation('price_stats');
// ['count' => 100, 'min' => 5.99, 'max' => 299.99, 'avg' => 45.50, 'sum' => 4550.00]

$avg = $result->aggregationValue('price_stats', 'avg');
// 45.50
```

### Bucket aggregation

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('by_category', Agg::terms('category')->size(10))
    ->execute();

$buckets = $result->buckets('by_category');
// Collection of buckets

foreach ($buckets as $bucket) {
    echo $bucket['key'] . ': ' . $bucket['doc_count'];
}
```

### Nested aggregation results

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregate('by_category', Agg::terms('category')
        ->agg('avg_price', Agg::avg('price'))
        ->agg('by_status', Agg::terms('status'))
    )
    ->execute();

foreach ($result->buckets('by_category') as $categoryBucket) {
    $category = $categoryBucket['key'];
    $count = $categoryBucket['doc_count'];
    $avgPrice = $categoryBucket['avg_price']['value'];

    echo "$category: $count books, avg price: $avgPrice\n";

    foreach ($categoryBucket['by_status']['buckets'] as $statusBucket) {
        echo "  - {$statusBucket['key']}: {$statusBucket['doc_count']}\n";
    }
}
```

### Raw aggregations

```php
// Full aggregation response
$aggs = $result->aggregations();

// Specific aggregation with all metadata
$termsAgg = $result->aggregation('by_category');
// [
//     'doc_count_error_upper_bound' => 0,
//     'sum_other_doc_count' => 50,
//     'buckets' => [...]
// ]
```

---

## Raw Aggregations

For complex aggregations not covered by the API:

```php
$result = Book::searchQuery(Query::matchAll())
    ->aggregateRaw([
        'price_percentiles' => [
            'percentiles' => [
                'field' => 'price',
                'percents' => [25, 50, 75, 95, 99],
            ],
        ],
        'significant_categories' => [
            'significant_terms' => [
                'field' => 'category',
                'min_doc_count' => 10,
            ],
        ],
    ])
    ->execute();
```

---

## Extending Agg

Add custom aggregation methods using macros:

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;

// In a service provider
Agg::macro('priceRanges', function () {
    return Agg::range('price')
        ->range(to: 25, key: 'budget')
        ->range(from: 25, to: 100, key: 'mid-range')
        ->range(from: 100, key: 'premium');
});

// Usage
$builder->aggregate('price_ranges', Agg::priceRanges());
```
