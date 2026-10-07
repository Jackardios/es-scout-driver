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
- Aggregations: `terms`, `histogram`, `date_histogram`, `range`, `geo_distance`, `filter`, `filters`, `global`, `nested`,
  `reverse_nested`, `composite`, `avg`, `sum`, `min`, `max`, `stats`, `extended_stats`, `cardinality`, `percentiles`,
  `top_hits`, `geo_bounds`, `geo_centroid`
- Sorting with multiple options
- Highlighting
- Suggestions
- Pagination with cursor support
- Multi-index search
- Soft deletes support

## Requirements

- PHP 8.2+ (Laravel 13 requires 8.3+)
- Laravel 12 or 13
- Laravel Scout 10.24+ or 11
- Elasticsearch 8.x or 9.x

Laravel 10 and 11 are supported by the 0.x line: `composer require jackardios/es-scout-driver:^0.1`.

The `elasticsearch/elasticsearch` client must have the same major version as the server. Composer installs the 9.x
client by default; for an Elasticsearch 8 server add `composer require elasticsearch/elasticsearch:^8.0` to your
application. See [Compatibility](docs/compatibility.md#php-client-compatibility).

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

## Scout Integration

- `Model::search('text')->get()` returns 10 models unless `take()` is called: Elasticsearch's default size.
- Scout 11's `semantic()` and `hybrid()` builder modes are ignored; use `Query::semantic()`, `Query::knn()` or
  `SearchBuilder::knn()`.
- A model that uses this package's `Searchable` trait works with the `elastic` and `null` drivers only; with
  `collection`, `database` or another engine it throws a `SearchException`.
- The package registers its own `null` driver (`Engine\NullEngine`) in place of Scout's, whichever driver is
  configured.
- With `scout.driver=elastic`, Scout's `RemoveFromSearch` job is replaced by `Jobs\RemoveFromSearch`, unless
  `Scout::removeFromSearchUsing()` already names another job. The job carries the index, id, routing and connection of
  each model instead of the models and sends the deletes to the client itself, so it does not call the engine: an
  engine wrapper does not see queued deletes. To change them, extend the job or write your own and register it with
  `Scout::removeFromSearchUsing()`. The job refuses a model without the package's trait, so an application that also
  indexes models with another Scout engine must register a job that handles both.

## Documentation

- [Search Builder](docs/search-builder.md) - Main search API
- [Queries](docs/queries.md) - All query types
- [Aggregations](docs/aggregations.md) - Aggregation types
- [Sorting](docs/sorting.md) - Sorting options
- [Search Results](docs/search-results.md) - Working with results
- [Configuration](docs/configuration.md) - Configuration options
- [Compatibility](docs/compatibility.md) - ES 8.x/9.x version notes

## Backward Compatibility

From 1.0.0 the package follows [Semantic Versioning](https://semver.org). Breaking changes to the public API ship only
in a new major version.

The public API is every public class, method, constant and property not marked `@internal`: `Searchable`,
`SearchBuilder`, `SearchResult`, `Hit`, `Suggestion`, `Paginator`, `SearchCursor`, the `Query`, `Agg` and `Sort`
factories and the classes they return, the enums, the exceptions and the configuration files.

- `QueryInterface`, `AggregationInterface`, `SortInterface` and `EngineInterface` may be implemented outside the
  package. New methods are added to them only in a major version.
- `Engine` is final. An engine of your own implements `EngineInterface` and extends `Laravel\Scout\Engines\Engine`,
  which the interface cannot express; to change the behaviour of `Engine`, hold one and forward to it. For a test
  engine, extend `Engine\NullEngine` (does nothing) and override what you need: it keeps working when a major version
  adds a method to `EngineInterface`. Register the engine under the `elastic` driver name with
  `EngineManager::extend()`: the package swaps the `RemoveFromSearch` job only for that name.
- `Jobs\RemoveFromSearch` may be extended; its `$operations` and `handle(Client $client)` are covered.
- `SearchBuilder` may be extended. Its state is private: a subclass works through the public methods.
- Parameter names are part of the public API: methods may be called with named arguments.
- The `Query\Concerns`, `Aggregations\Concerns` and `Sort\Concerns` traits are not covered: their methods belong to the public API of
  the classes that use them, but using a trait in your own class may break in a minor release.
- `@internal` code (the engine helpers, model resolution, the `fromRaw()` factories, the `Paginator` and
  `SearchCursor` constructors and every `SearchResult` constructor argument after `$raw`) may change in any release.

## License

MIT License. See [LICENSE](LICENSE) for details.
