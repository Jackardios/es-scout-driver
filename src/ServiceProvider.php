<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Jackardios\EsScoutDriver\Engine\Engine;
use Jackardios\EsScoutDriver\Engine\NullEngine;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch;
use Illuminate\Support\ServiceProvider as AbstractServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Jobs\RemoveFromSearch as DefaultRemoveFromSearch;
use Laravel\Scout\Scout;

final class ServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/elastic.client.php', 'elastic.client');
        $this->mergeConfigFrom(__DIR__ . '/../config/elastic.scout.php', 'elastic.scout');

        $this->registerConnections();

        $this->app->singleton(Engine::class, function () {
            $defaultConnection = $this->app['config']->get('elastic.client.default', 'default');

            return new Engine(
                $this->app->make("elastic.client.connection.$defaultConnection"),
                (bool) $this->app['config']->get('elastic.scout.refresh_documents', false),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/elastic.client.php' => $this->app->configPath('elastic.client.php'),
        ], 'elastic-client-config');

        $this->publishes([
            __DIR__ . '/../config/elastic.scout.php' => $this->app->configPath('elastic.scout.php'),
        ], 'elastic-scout-config');

        /** @var EngineManager $engineManager */
        $engineManager = $this->app->make(EngineManager::class);

        $engineManager->extend('elastic', fn() => $this->app->make(Engine::class));
        $engineManager->extend('null', fn() => new NullEngine());

        if (
            $this->app['config']->get('scout.driver') === 'elastic'
            && property_exists(Scout::class, 'removeFromSearchJob')
            && Scout::$removeFromSearchJob === DefaultRemoveFromSearch::class
        ) {
            Scout::removeFromSearchUsing(RemoveFromSearch::class);
        }
    }

    private function registerConnections(): void
    {
        $connections = $this->app['config']->get('elastic.client.connections', []);
        $default = $this->app['config']->get('elastic.client.default', 'default');

        foreach ($connections as $name => $connectionConfig) {
            $this->app->singleton(
                "elastic.client.connection.$name",
                fn() => ClientBuilder::fromConfig($connectionConfig)
            );
        }

        $this->app->singleton(Client::class, function () use ($default) {
            return $this->app->make("elastic.client.connection.$default");
        });
    }
}
