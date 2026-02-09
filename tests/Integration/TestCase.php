<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Integration;

use Elastic\Elasticsearch\Client;
use Jackardios\EsScoutDriver\ServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->app->make(Client::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ScoutServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('scout.driver', 'elastic');
        $app['config']->set('elastic.client.connections.default.hosts', [env('ELASTIC_HOST', 'localhost:9200')]);
        $app['config']->set('elastic.scout.refresh_documents', true);

        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '23306'),
            'database' => env('DB_DATABASE', 'test'),
            'username' => env('DB_USERNAME', 'test'),
            'password' => env('DB_PASSWORD', 'test'),
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../App/database/migrations');
    }

    protected function createIndex(string $name, array $body = []): void
    {
        if ($this->indexExists($name)) {
            $this->deleteIndex($name);
        }

        $params = ['index' => $name];

        if (!empty($body)) {
            $params['body'] = $body;
        }

        $this->client->indices()->create($params);
    }

    protected function deleteIndex(string $name): void
    {
        if ($this->indexExists($name)) {
            $this->client->indices()->delete(['index' => $name]);
        }
    }

    protected function indexExists(string $name): bool
    {
        return $this->client->indices()->exists(['index' => $name])->asBool();
    }

    protected function refreshIndex(string $name): void
    {
        $this->client->indices()->refresh(['index' => $name]);
    }
}
