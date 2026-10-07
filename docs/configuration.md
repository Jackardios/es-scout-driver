# Configuration

This document covers all configuration options for the ES Scout Driver.

## Table of Contents

- [Installation](#installation)
- [Environment Variables](#environment-variables)
- [Elasticsearch Client Config](#elasticsearch-client-config)
- [Scout Config](#scout-config)
- [Model Configuration](#model-configuration)

---

## Installation

Publish the configuration files:

```bash
php artisan vendor:publish --provider="Jackardios\EsScoutDriver\ServiceProvider"
```

This creates:
- `config/elastic.client.php` - Elasticsearch client configuration
- `config/elastic.scout.php` - Scout driver configuration

---

## Environment Variables

Add these to your `.env` file:

```env
# Scout driver
SCOUT_DRIVER=elastic

# Elasticsearch connection
ELASTIC_HOST=localhost:9200
ELASTIC_CONNECTION=default

# Optional: Refresh documents immediately
ELASTIC_REFRESH_DOCUMENTS=false

# Optional: Model hydration mismatch strategy: ignore, log, exception
ELASTIC_MODEL_HYDRATION_MISMATCH=ignore

# Optional: Bulk failure mode: exception, log, ignore
ELASTIC_BULK_FAILURE_MODE=exception

# Optional: Query type of Scout's search(): simple_query_string, query_string
ELASTIC_SCOUT_QUERY_TYPE=simple_query_string
```

---

## Elasticsearch Client Config

`config/elastic.client.php`:

> **Compatibility Note:** This configuration format is fully compatible with [babenkoivan/elastic-client](https://github.com/babenkoivan/elastic-client) and [babenkoivan/elastic-migrations](https://github.com/babenkoivan/elastic-migrations). You can use these packages alongside es-scout-driver without any configuration conflicts.
>
> **Key Format Note:** Connection options are passed directly to `Elastic\Elasticsearch\ClientBuilder::fromConfig()`. Use option names matching its `set...` methods (for example, `basicAuthentication`, `apiKey`, `elasticCloudId`, `caBundle`). Options mapped to multi-argument setters (for example, `apiKey`, `sslCert`, `sslKey`, `basicAuthentication`) must be provided as arrays.

```php
return [
    'default' => env('ELASTIC_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTIC_HOST', 'localhost:9200'),
            ],
        ],
    ],
];
```

### Multiple Hosts

```php
'connections' => [
    'default' => [
        'hosts' => [
            'node1.example.com:9200',
            'node2.example.com:9200',
            'node3.example.com:9200',
        ],
    ],
],
```

### Authentication

**Basic Auth:**

```php
'connections' => [
    'default' => [
        'hosts' => ['https://localhost:9200'],
        'basicAuthentication' => [env('ELASTIC_USER'), env('ELASTIC_PASSWORD')],
    ],
],
```

**API Key:**

```php
'connections' => [
    'default' => [
        'hosts' => ['https://localhost:9200'],
        'apiKey' => [env('ELASTIC_API_KEY')],
        // Or with explicit key id:
        // 'apiKey' => [env('ELASTIC_API_KEY'), env('ELASTIC_API_KEY_ID')],
    ],
],
```

**Elastic Cloud:**

```php
'connections' => [
    'cloud' => [
        'elasticCloudId' => env('ELASTIC_CLOUD_ID'),
        'apiKey' => [env('ELASTIC_API_KEY')],
    ],
],
```

### SSL/TLS

```php
'connections' => [
    'default' => [
        'hosts' => ['https://localhost:9200'],
        'sslVerification' => true,
        'sslCert' => ['/path/to/cert.pem'],
        'sslKey' => ['/path/to/key.pem'],
        'caBundle' => '/path/to/ca.pem',
    ],
],
```

### Multiple Connections

```php
'default' => 'production',

'connections' => [
    'production' => [
        'hosts' => ['https://prod-es.example.com:9200'],
        'apiKey' => [env('ELASTIC_PROD_API_KEY')],
    ],
    'analytics' => [
        'hosts' => ['https://analytics-es.example.com:9200'],
        'apiKey' => [env('ELASTIC_ANALYTICS_API_KEY')],
    ],
],
```

Use in model:

```php
class AnalyticsEvent extends Model
{
    use Searchable;

    public function searchableConnection(): ?string
    {
        return 'analytics';
    }
}
```

When using multiple connections:
- `null` or `''` from `searchableConnection()` means the default connection; a name missing from `connections` throws
  an `InvalidArgumentException`.
- `searchQuery()->join(...)` supports only models that share the same `searchableConnection()`.
- Indexing and delete bulk operations are grouped per model connection automatically.

---

## Scout Config

`config/elastic.scout.php`:

```php
return [
    // Refresh documents immediately after write operations
    'refresh_documents' => env('ELASTIC_REFRESH_DOCUMENTS', false),

    // Behavior when hits cannot be hydrated into models
    'model_hydration_mismatch' => env('ELASTIC_MODEL_HYDRATION_MISMATCH', 'ignore'),

    // Behavior when some documents of a bulk index or delete request fail
    'bulk_failure_mode' => env('ELASTIC_BULK_FAILURE_MODE', 'exception'),

    // Query type of Scout's basic search()
    'scout_query_type' => env('ELASTIC_SCOUT_QUERY_TYPE', 'simple_query_string'),
];
```

`model_hydration_mismatch`, `bulk_failure_mode` and `scout_query_type` are trimmed and case-insensitive; any other
value throws an `InvalidArgumentException` naming the key.

### Refresh Documents

When `true`, documents are immediately available for search after indexing. This impacts performance but ensures consistency.

```php
'refresh_documents' => true,  // Immediate consistency
'refresh_documents' => false, // Better performance (default)
```

### Model Hydration Mismatch

Controls behavior when Elasticsearch hits cannot be hydrated into Eloquent models (for example, if `modifyQuery()` or `modifyModels()` filters records):

```php
'model_hydration_mismatch' => 'ignore',    // default, silently skip missing models
'model_hydration_mismatch' => 'log',       // log warning and skip
'model_hydration_mismatch' => 'exception', // throw ModelHydrationMismatchException
```

### Bulk Failure Mode

Controls what happens when Elasticsearch rejects some documents of a bulk request sent by indexing (`save()`,
`searchable()`, `scout:import`) or removal (`delete()`, `unsearchable()`, the `RemoveFromSearch` job):

```php
'bulk_failure_mode' => 'exception', // default, throw BulkOperationException (getFailedDocuments() lists them)
'bulk_failure_mode' => 'log',       // log an error with the failed documents and continue
'bulk_failure_mode' => 'ignore',    // silently continue
```

### Scout Query Type

Controls which Elasticsearch query type is used for Scout's basic search (`Model::search('query')`). This does NOT affect `SearchBuilder`.

```php
'scout_query_type' => 'simple_query_string', // default, safer limited syntax
'scout_query_type' => 'query_string',        // full Lucene syntax
```

**Security note:** `query_string` accepts full Lucene syntax including `field:value` queries. If user input is passed directly to `search()`, users could query unintended fields (e.g., `password:*`). Use `simple_query_string` (the default) when search input comes from untrusted sources.

### Scout Search Callback

The callback of `Model::search($query, $callback)` receives the Elasticsearch client, the query string and the request
parameters built by the engine. It returns the client's response or its array:

```php
use Elastic\Elasticsearch\Client;

Book::search('dune', function (Client $client, ?string $query, array $params) {
    $params['body']['min_score'] = 1.0;

    return $client->search($params);
})->get();
```

### Laravel Scout Config

`config/scout.php`:

```php
return [
    'driver' => env('SCOUT_DRIVER', 'elastic'),

    // Soft delete handling
    'soft_delete' => false,

    // Queue indexing operations
    'queue' => false,

    // Identify searchable models
    'identify' => false,
];
```

---

## Model Configuration

### Basic Model

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
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
```

### Custom Index Name

```php
public function searchableAs(): string
{
    return 'custom_books_index';
}
```

The name may also be an alias, a data stream, a wildcard pattern or a comma-separated list. A search of one model
maps every hit to that model without asking Elasticsearch. A search of several joined models, or one with a
`pointInTime()` (`cursor()` and `chunk()` included), has to know which indices each name covers when a hit comes
from another index name: it asks Elasticsearch once per search, or once per cursor (the get settings API, which needs
the `view_index_metadata` privilege; without it the request fails with 403 and so does the search).

### Custom Document ID

```php
public function getScoutKey(): mixed
{
    return $this->uuid;
}

public function getScoutKeyName(): string
{
    return 'uuid';
}
```

### Custom Routing

For parent-child relationships or custom shard distribution:

```php
public function searchableRouting(): ?string
{
    return (string) $this->tenant_id;
}
```

### Eager Loading for Indexing

Relations to load before `toSearchableArray()` runs, once per batch of indexed models:

```php
public function searchableWith(): array
{
    return ['author', 'categories'];
}
```

To eager load relations of search results, use `with()` on the search builder (see
[Eager Loading](search-builder.md#eager-loading)).

### Custom Connection

```php
public function searchableConnection(): ?string
{
    return 'analytics';
}
```

### Conditional Indexing

```php
public function shouldBeSearchable(): bool
{
    return $this->status === 'published';
}
```

---

## Index Management

### Creating Index

Create your Elasticsearch index before importing data. We recommend using [babenkoivan/elastic-migrations](https://github.com/babenkoivan/elastic-migrations) for managing indices:

```bash
composer require babenkoivan/elastic-migrations
php artisan vendor:publish --provider="Elastic\Migrations\ServiceProvider"
```

Create a migration:

```bash
php artisan elastic:make:migration create_books_index
```

```php
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateBooksIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('books', function ($mapping, $settings) {
            $mapping->text('title');
            $mapping->keyword('author');
            $mapping->float('price');
            $mapping->date('published_at');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('books');
    }
}
```

Run migrations:

```bash
php artisan elastic:migrate
```

> **Note:** The `config/elastic.client.php` configuration is fully compatible with [babenkoivan/elastic-client](https://github.com/babenkoivan/elastic-client) and [babenkoivan/elastic-migrations](https://github.com/babenkoivan/elastic-migrations). You can use these packages together without any configuration conflicts.

### Importing Data

```bash
php artisan scout:import "App\Models\Book"
```

### Flushing Index

```bash
php artisan scout:flush "App\Models\Book"
```

---

## Soft Deletes

Enable soft delete handling in `config/scout.php`:

```php
'soft_delete' => true,
```

This adds a `__soft_deleted` field to indexed documents. Soft-deleted models are automatically filtered from search results.

Query soft-deleted documents:

```php
use Jackardios\EsScoutDriver\Support\Query;

// Include soft-deleted
Book::searchQuery(Query::matchAll())->withTrashed()->execute();

// Only soft-deleted
Book::searchQuery(Query::matchAll())->onlyTrashed()->execute();
```

---

## Queuing

Enable queued indexing in `config/scout.php`:

```php
'queue' => true,

// Or with specific connection/queue
'queue' => [
    'connection' => 'redis',
    'queue' => 'scout',
],
```

This queues model indexing operations for better performance. Removals use the package's `RemoveFromSearch` job,
which stores the index, id, routing and connection of each document so it works after the rows are gone; like Scout's
jobs it takes `tries`, `backoff` and `max_exceptions` from `scout.jobs`.

---

## Testing

### Disabling Searchable

In tests, you may want to disable search indexing:

```php
use Laravel\Scout\ModelObserver;

// In TestCase setUp
ModelObserver::disableSyncingFor(Book::class);
```

### Using Null Engine

For tests that don't need Elasticsearch:

```php
// In .env.testing
SCOUT_DRIVER=null
```

Or set it at runtime in tests:

```php
config()->set('scout.driver', 'null');
```
