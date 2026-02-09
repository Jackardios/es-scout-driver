# Sorting

This document covers sorting options for search results.

```php
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\EsScoutDriver\Enums\SortOrder;
```

## Table of Contents

- [Basic Sorting](#basic-sorting)
- [Sort Factory](#sort-factory)
- [Field Sort](#field-sort)
- [Score Sort](#score-sort)
- [Geo Distance Sort](#geo-distance-sort)
- [Script Sort](#script-sort)
- [Raw Sort](#raw-sort)

---

## Basic Sorting

The simplest way to add sorting:

```php
// Single field
Book::searchQuery(Query::matchAll())
    ->sort('created_at', 'desc')
    ->execute();

// Multiple fields
Book::searchQuery(Query::matchAll())
    ->sort('featured', 'desc')
    ->sort('created_at', 'desc')
    ->sort('_score', 'desc')
    ->execute();

// Using enum
Book::searchQuery(Query::matchAll())
    ->sort('price', SortOrder::Asc)
    ->execute();
```

With options:

```php
Book::searchQuery(Query::matchAll())
    ->sort('price', 'asc', missing: '_last', mode: 'avg', unmappedType: 'float')
    ->execute();
```

---

## Sort Factory

Use the `Sort` factory for more control:

```php
use Jackardios\EsScoutDriver\Sort\Sort;

Book::searchQuery(Query::matchAll())
    ->sort(Sort::field('price')->desc()->missingLast())
    ->sort(Sort::score())
    ->execute();
```

---

## Field Sort

Sort by a field value:

```php
// Basic
Sort::field('price')                    // Ascending by default
Sort::field('price')->asc()
Sort::field('price')->desc()

// With order method
Sort::field('price')->order('desc')
Sort::field('price')->order(SortOrder::Desc)

// Missing values handling
Sort::field('price')->missing('_last')  // Put missing values last
Sort::field('price')->missing('_first') // Put missing values first
Sort::field('price')->missing(0)        // Use 0 for missing values
Sort::field('price')->missingLast()     // Shorthand
Sort::field('price')->missingFirst()    // Shorthand

// Mode for multi-valued fields
Sort::field('ratings')->mode('avg')     // avg, min, max, sum, median
Sort::field('ratings')->mode('max')

// Unmapped field handling
Sort::field('price')->unmappedType('float')

// Numeric type
Sort::field('date_field')->numericType('date')

// Format for dates
Sort::field('timestamp')->format('strict_date_time')
```

### Nested field sorting

```php
Sort::field('comments.rating')
    ->nested([
        'path' => 'comments',
        'filter' => ['term' => ['comments.status' => 'approved']],
    ])
    ->mode('avg')
```

### Complete example

```php
Book::searchQuery(Query::matchAll())
    ->sort(
        Sort::field('price')
            ->desc()
            ->missingLast()
            ->unmappedType('float')
    )
    ->execute();
```

---

## Score Sort

Sort by relevance score:

```php
Sort::score()                   // Descending by default (highest scores first)
Sort::score()->asc()            // Ascending (lowest scores first)
Sort::score()->desc()
```

Combine with field sorts:

```php
Book::searchQuery(Query::match('title', 'elasticsearch'))
    ->sort(Sort::field('featured')->desc())
    ->sort(Sort::score())
    ->sort(Sort::field('created_at')->desc())
    ->execute();
```

---

## Geo Distance Sort

Sort by distance from a geographic point:

```php
// Basic (ascending by default - nearest first)
Sort::geoDistance('location', 52.3676, 4.9041)

// With options
Sort::geoDistance('location', 52.3676, 4.9041)
    ->asc()                         // Nearest first
    ->desc()                        // Farthest first
    ->unit('km')                    // km, m, mi, yd, ft
    ->mode('min')                   // min, max, avg, median (for multi-valued)
    ->distanceType('arc')           // arc (accurate) or plane (fast)
    ->ignoreUnmapped(true)
```

Example:

```php
Store::searchQuery(Query::matchAll())
    ->sort(
        Sort::geoDistance('location', 52.3676, 4.9041)
            ->asc()
            ->unit('km')
    )
    ->execute();
```

---

## Script Sort

Sort using a custom script:

```php
// Basic
Sort::script(
    ['source' => "doc['price'].value * doc['discount'].value"],
    'number'
)

// With parameters
Sort::script(
    [
        'source' => "doc['price'].value * params.factor",
        'params' => ['factor' => 0.9],
        'lang' => 'painless',
    ],
    'number'
)
    ->desc()
    ->mode('avg')

// String type
Sort::script(
    ['source' => "doc['title'].value.toLowerCase()"],
    'string'
)
    ->asc()
```

Example - sort by computed value:

```php
Book::searchQuery(Query::matchAll())
    ->sort(
        Sort::script(
            [
                'source' => "doc['price'].value - (doc['price'].value * doc['discount'].value / 100)",
                'lang' => 'painless',
            ],
            'number'
        )->asc()
    )
    ->execute();
```

---

## Raw Sort

For complete control, use raw sort arrays:

```php
Book::searchQuery(Query::matchAll())
    ->sortRaw([
        ['featured' => 'desc'],
        ['_score' => 'desc'],
        [
            'price' => [
                'order' => 'asc',
                'missing' => '_last',
                'unmapped_type' => 'float',
            ],
        ],
        [
            '_geo_distance' => [
                'location' => [4.9041, 52.3676],
                'order' => 'asc',
                'unit' => 'km',
            ],
        ],
    ])
    ->execute();
```

---

## Combining Sorts

Multiple sorts are applied in order:

```php
Book::searchQuery(Query::match('title', 'guide'))
    // First: featured items on top
    ->sort(Sort::field('featured')->desc())
    // Second: by relevance score
    ->sort(Sort::score())
    // Third: by date for items with same score
    ->sort(Sort::field('created_at')->desc())
    // Fourth: by ID for consistent ordering
    ->sort(Sort::field('_id'))
    ->execute();
```

---

## Clearing Sort

Remove all sort criteria:

```php
$builder = Book::searchQuery(Query::matchAll())
    ->sort('price', 'desc');

// Later...
$builder->clearSort();
```

---

## Extending Sort

Add custom sort methods using macros:

```php
use Jackardios\EsScoutDriver\Sort\Sort;

// In a service provider
Sort::macro('byPopularity', function () {
    return Sort::script(
        [
            'source' => "doc['views'].value * 0.3 + doc['likes'].value * 0.7",
            'lang' => 'painless',
        ],
        'number'
    )->desc();
});

// Usage
$builder->sort(Sort::byPopularity());
```
