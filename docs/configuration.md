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
```

---

## Elasticsearch Client Config

`config/elastic.client.php`:

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
        'hosts' => [
            [
                'host' => 'localhost',
                'port' => 9200,
                'scheme' => 'https',
                'user' => env('ELASTIC_USER'),
                'pass' => env('ELASTIC_PASSWORD'),
            ],
        ],
    ],
],
```

**API Key:**

```php
'connections' => [
    'default' => [
        'hosts' => ['https://localhost:9200'],
        'apiKey' => env('ELASTIC_API_KEY'),
    ],
],
```

**Elastic Cloud:**

```php
'connections' => [
    'cloud' => [
        'cloudId' => env('ELASTIC_CLOUD_ID'),
        'apiKey' => env('ELASTIC_API_KEY'),
    ],
],
```

### SSL/TLS

```php
'connections' => [
    'default' => [
        'hosts' => ['https://localhost:9200'],
        'sslVerification' => true,
        'sslCert' => '/path/to/cert.pem',
        'sslKey' => '/path/to/key.pem',
        'sslCA' => '/path/to/ca.pem',
    ],
],
```

### Multiple Connections

```php
'default' => 'production',

'connections' => [
    'production' => [
        'hosts' => ['https://prod-es.example.com:9200'],
        'apiKey' => env('ELASTIC_PROD_API_KEY'),
    ],
    'analytics' => [
        'hosts' => ['https://analytics-es.example.com:9200'],
        'apiKey' => env('ELASTIC_ANALYTICS_API_KEY'),
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

---

## Scout Config

`config/elastic.scout.php`:

```php
return [
    // Refresh documents immediately after write operations
    'refresh_documents' => env('ELASTIC_REFRESH_DOCUMENTS', false),
];
```

### Refresh Documents

When `true`, documents are immediately available for search after indexing. This impacts performance but ensures consistency.

```php
'refresh_documents' => true,  // Immediate consistency
'refresh_documents' => false, // Better performance (default)
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

### Eager Loading for Search Results

```php
public function searchableWith(): array
{
    return ['author', 'categories'];
}
```

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

Create the index before importing data:

```bash
php artisan make:elastic-index books
```

Or use migration-style index creation:

```php
use Elastic\Elasticsearch\Client;

class CreateBooksIndex
{
    public function up(Client $client): void
    {
        $client->indices()->create([
            'index' => 'books',
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 1,
                ],
                'mappings' => [
                    'properties' => [
                        'title' => ['type' => 'text'],
                        'author' => ['type' => 'keyword'],
                        'price' => ['type' => 'float'],
                        'published_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ]);
    }
}
```

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
// Include soft-deleted
Book::searchQuery()
    ->boolQuery()->withTrashed()
    ->must(Query::matchAll())
    ->execute();

// Only soft-deleted
Book::searchQuery()
    ->boolQuery()->onlyTrashed()
    ->must(Query::matchAll())
    ->execute();
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

This queues model indexing operations for better performance.

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

Or use the package's NullEngine:

```php
use Jackardios\EsScoutDriver\Engine\NullEngine;

$this->app->bind(EngineInterface::class, NullEngine::class);
```
