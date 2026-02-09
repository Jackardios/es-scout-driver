# Aggregations

Aggregations allow you to group and extract statistics from your data. They are created using the `Agg` factory class.

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;
```

## Table of Contents

- [Using Aggregations](#using-aggregations)
- [Metric Aggregations](#metric-aggregations)
- [Bucket Aggregations](#bucket-aggregations)
- [Sub-Aggregations](#sub-aggregations)
- [Accessing Results](#accessing-results)

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
    ->addRange(to: 20)
    ->addRange(from: 20, to: 50)
    ->addRange(from: 50, to: 100)
    ->addRange(from: 100)

// With keys
Agg::range('price')
    ->addRange(to: 20, key: 'cheap')
    ->addRange(from: 20, to: 50, key: 'medium')
    ->addRange(from: 50, key: 'expensive')
```

---

## Sub-Aggregations

Nest aggregations within bucket aggregations:

```php
// Single level
Agg::terms('category')
    ->size(10)
    ->subAggregation('avg_price', Agg::avg('price'))
    ->subAggregation('max_price', Agg::max('price'))

// Multiple levels
Agg::terms('category')
    ->subAggregation('by_author', Agg::terms('author')
        ->size(5)
        ->subAggregation('total_sales', Agg::sum('sales'))
    )

// Date histogram with stats
Agg::dateHistogram('created_at', '1M')
    ->subAggregation('price_stats', Agg::stats('price'))
    ->subAggregation('by_status', Agg::terms('status'))
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
        ->subAggregation('avg_price', Agg::avg('price'))
        ->subAggregation('by_status', Agg::terms('status'))
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
        ->addRange(to: 25, key: 'budget')
        ->addRange(from: 25, to: 100, key: 'mid-range')
        ->addRange(from: 100, key: 'premium');
});

// Usage
$builder->aggregate('price_ranges', Agg::priceRanges());
```
