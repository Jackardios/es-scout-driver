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
            /** @var \Illuminate\Config\Repository $config */
            $config = $this->app->make('config');
            $defaultConnection = $config->get('elastic.client.default', 'default');

            return new Engine(
                $this->app->make("elastic.client.connection.$defaultConnection"),
                (bool) $config->get('elastic.scout.refresh_documents', false),
                $defaultConnection,
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

        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');
        if (
            $config->get('scout.driver') === 'elastic'
            && Scout::$removeFromSearchJob === DefaultRemoveFromSearch::class
        ) {
            Scout::removeFromSearchUsing(RemoveFromSearch::class);
        }
    }

    private function registerConnections(): void
    {
        /** @var \Illuminate\Config\Repository $config */
        $config = $this->app->make('config');
        /** @var array<string, array<string, mixed>> $connections */
        $connections = $config->get('elastic.client.connections', []);
        $default = $config->get('elastic.client.default', 'default');

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
