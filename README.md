# ES Scout Driver

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jackardios/es-scout-driver.svg)](https://packagist.org/packages/jackardios/es-scout-driver)                                                  
[![PHP Version](https://img.shields.io/packagist/php-v/jackardios/es-scout-driver.svg)](https://packagist.org/packages/jackardios/es-scout-driver)                                                             
[![CI](https://github.com/jackardios/es-scout-driver/actions/workflows/ci.yml/badge.svg)](https://github.com/jackardios/es-scout-driver/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

Advanced Elasticsearch driver for Laravel Scout with full Query DSL support.

## Features

- Full Elasticsearch Query DSL support
- Fluent API for building complex queries
- Bool queries with `must`, `should`, `filter`, `mustNot`
- Full-text queries: `match`, `multi_match`, `match_phrase`, `query_string`
- Term-level queries: `term`, `terms`, `range`, `exists`, `prefix`, `wildcard`, `regexp`, `fuzzy`, `ids`
- Geo queries: `geo_distance`, `geo_bounding_box`, `geo_shape`
- Compound queries: `bool`, `nested`, `function_score`, `dis_max`, `boosting`, `constant_score`
- Joining queries: `has_child`, `has_parent`, `parent_id`
- Aggregations: `terms`, `avg`, `sum`, `min`, `max`, `stats`, `cardinality`, `histogram`, `date_histogram`, `range`
- Sorting with multiple options
- Highlighting
- Suggestions
- Pagination with cursor support
- Multi-index search
- Soft deletes support

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- Elasticsearch 8.x or 9.x

## Installation

```bash
composer require jackardios/es-scout-driver
```

Publish the configuration files:

```bash
php artisan vendor:publish --provider="Jackardios\EsScoutDriver\ServiceProvider"
```

Configure your Elasticsearch connection in `.env`:

```env
SCOUT_DRIVER=elastic

ELASTIC_HOST=localhost:9200
```

## Quick Start

### 1. Add the Searchable trait to your model

```php
use Jackardios\EsScoutDriver\Searchable;

class Book extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'price' => $this->price,
            'published_at' => $this->published_at,
        ];
    }
}
```

### 2. Create your index (optional but recommended)

For index management, we recommend [babenkoivan/elastic-migrations](https://github.com/babenkoivan/elastic-migrations):

```bash
composer require babenkoivan/elastic-migrations
php artisan elastic:make:migration create_books_index
php artisan elastic:migrate
```

> **Note:** The `config/elastic.client.php` is compatible with elastic-migrations.

### 3. Index your data

```bash
php artisan scout:import "App\Models\Book"
```

### 4. Search

```php
use Jackardios\EsScoutDriver\Support\Query;

// Simple search
$books = Book::searchQuery(Query::match('title', 'laravel'))->execute();

// Complex search with bool query
$books = Book::searchQuery()
    ->must(Query::match('title', 'laravel'))
    ->filter(Query::term('status', 'published'))
    ->filter(Query::range('price')->gte(10)->lte(50))
    ->sort('published_at', 'desc')
    ->size(20)
    ->execute();

// Get models
$models = $books->models();

// Get total count
$total = $books->total;
```

## Basic Usage

### Match Query

```php
Book::searchQuery(Query::match('title', 'elasticsearch'))->execute();

// With options
Book::searchQuery(
    Query::match('title', 'elasticsearch')
        ->fuzziness('AUTO')
        ->operator('and')
)->execute();
```

### Multi-Match Query

```php
Book::searchQuery(
    Query::multiMatch(['title', 'description'], 'search text')
        ->type('best_fields')
        ->fuzziness('AUTO')
)->execute();
```

### Bool Query

```php
Book::searchQuery()
    ->must(Query::match('title', 'laravel'))
    ->must(Query::match('description', 'framework'))
    ->should(Query::term('featured', true))
    ->filter(Query::range('price')->lte(100))
    ->mustNot(Query::term('status', 'draft'))
    ->execute();
```

### Range Query

```php
Book::searchQuery(
    Query::range('price')->gte(10)->lte(50)
)->execute();

// Date range
Book::searchQuery(
    Query::range('published_at')
        ->gte('2024-01-01')
        ->lte('now')
        ->format('yyyy-MM-dd')
)->execute();
```

### Sorting

```php
use Jackardios\EsScoutDriver\Sort\Sort;

Book::searchQuery(Query::matchAll())
    ->sort('price', 'asc')
    ->sort('_score', 'desc')
    ->execute();

// Advanced sorting
Book::searchQuery(Query::matchAll())
    ->sort(Sort::field('price')->desc()->missing('_last'))
    ->sort(Sort::score())
    ->execute();
```

### Pagination

```php
// Standard pagination
$paginator = Book::searchQuery(Query::matchAll())
    ->paginate(perPage: 15, pageName: 'page', page: 1);

// Access in Blade
@foreach ($paginator->models() as $book)
    {{ $book->title }}
@endforeach

{{ $paginator->links() }}
```

`perPage` must be greater than `0`, and `page` must be greater than or equal to `1`.

### Aggregations

```php
use Jackardios\EsScoutDriver\Aggregations\Agg;

$result = Book::searchQuery(Query::matchAll())
    ->aggregate('avg_price', Agg::avg('price'))
    ->aggregate('by_author', Agg::terms('author')->size(10))
    ->execute();

// Get aggregation results
$avgPrice = $result->aggregationValue('avg_price');
$authorBuckets = $result->buckets('by_author');
```

### Highlighting

```php
$result = Book::searchQuery(Query::match('title', 'laravel'))
    ->highlight('title', preTags: ['<em>'], postTags: ['</em>'])
    ->highlight('description')
    ->execute();

foreach ($result->hits() as $hit) {
    $highlights = $hit->highlight; // ['title' => ['<em>Laravel</em> Guide']]
}
```

## Documentation

- [Search Builder](docs/search-builder.md) - Main search API
- [Queries](docs/queries.md) - All query types
- [Aggregations](docs/aggregations.md) - Aggregation types
- [Sorting](docs/sorting.md) - Sorting options
- [Search Results](docs/search-results.md) - Working with results
- [Configuration](docs/configuration.md) - Configuration options
- [Compatibility](docs/compatibility.md) - ES 8.x/9.x version notes

## License

MIT License. See [LICENSE](LICENSE) for details.
