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

    private static bool $servicesChecked = false;
    private static ?string $skipReason = null;

    protected Client $client;

    protected function setUp(): void
    {
        $this->ensureServicesAreReachable();

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
        if (!isset($this->client)) {
            return;
        }

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
        if (!isset($this->client)) {
            return;
        }

        if ($this->indexExists($name)) {
            $this->client->indices()->delete(['index' => $name]);
        }
    }

    protected function indexExists(string $name): bool
    {
        if (!isset($this->client)) {
            return false;
        }

        return $this->client->indices()->exists(['index' => $name])->asBool();
    }

    protected function refreshIndex(string $name): void
    {
        if (!isset($this->client)) {
            return;
        }

        $this->client->indices()->refresh(['index' => $name]);
    }

    private function ensureServicesAreReachable(): void
    {
        if (self::$servicesChecked) {
            if (self::$skipReason !== null) {
                $this->markTestSkipped(self::$skipReason);
            }

            return;
        }

        self::$servicesChecked = true;

        $dbHost = (string) env('DB_HOST', '127.0.0.1');
        $dbPort = (int) env('DB_PORT', 23306);
        [$elasticHost, $elasticPort] = $this->parseHostAndPort((string) env('ELASTIC_HOST', '127.0.0.1:29200'), 9200);

        $unavailable = [];

        if (!$this->isTcpServiceReachable($dbHost, $dbPort)) {
            $unavailable[] = sprintf('MySQL at %s:%d', $dbHost, $dbPort);
        }

        if (!$this->isTcpServiceReachable($elasticHost, $elasticPort)) {
            $unavailable[] = sprintf('Elasticsearch at %s:%d', $elasticHost, $elasticPort);
        }

        if ($unavailable !== []) {
            self::$skipReason = 'Integration dependencies are unavailable (' . implode(', ', $unavailable) . '). Start them with `make up wait`.';
            $this->markTestSkipped(self::$skipReason);
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseHostAndPort(string $rawHost, int $defaultPort): array
    {
        $rawHost = trim(explode(',', $rawHost)[0] ?? '');

        if ($rawHost === '') {
            return ['127.0.0.1', $defaultPort];
        }

        if (str_contains($rawHost, '://')) {
            $parsed = parse_url($rawHost);
            if ($parsed !== false && isset($parsed['host'])) {
                return [$parsed['host'], (int) ($parsed['port'] ?? $defaultPort)];
            }
        }

        if (str_contains($rawHost, ':')) {
            [$host, $port] = explode(':', $rawHost, 2);
            if ($host !== '') {
                return [$host, (int) ($port !== '' ? $port : $defaultPort)];
            }
        }

        return [$rawHost, $defaultPort];
    }

    private function isTcpServiceReachable(string $host, int $port): bool
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $connection = @stream_socket_client("tcp://{$host}:{$port}", $errno, $error, 0.5);

            if (is_resource($connection)) {
                fclose($connection);

                return true;
            }

            usleep(200000);
        }

        return false;
    }
}
