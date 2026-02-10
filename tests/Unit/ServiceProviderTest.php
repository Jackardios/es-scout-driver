<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Jackardios\EsScoutDriver\Engine\Engine;
use Jackardios\EsScoutDriver\Engine\NullEngine;
use Jackardios\EsScoutDriver\Jobs\RemoveFromSearch as ElasticRemoveFromSearch;
use Jackardios\EsScoutDriver\ServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Jobs\RemoveFromSearch as DefaultRemoveFromSearch;
use Laravel\Scout\Scout;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ScoutServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('elastic.client.default', 'default');
        $app['config']->set('elastic.client.connections.default.hosts', ['localhost:9200']);
        $app['config']->set('elastic.scout.refresh_documents', false);
        $app['config']->set('scout.driver', 'elastic');
    }

    protected function tearDown(): void
    {
        Scout::removeFromSearchUsing(DefaultRemoveFromSearch::class);

        parent::tearDown();
    }

    #[Test]
    public function it_registers_elastic_client_connection(): void
    {
        $this->assertTrue($this->app->bound('elastic.client.connection.default'));
    }

    #[Test]
    public function it_registers_client_class_binding(): void
    {
        $this->assertTrue($this->app->bound(Client::class));
    }

    #[Test]
    public function it_registers_engine_singleton(): void
    {
        $this->assertTrue($this->app->bound(Engine::class));

        $engine1 = $this->app->make(Engine::class);
        $engine2 = $this->app->make(Engine::class);

        $this->assertSame($engine1, $engine2);
    }

    #[Test]
    public function it_extends_engine_manager_with_elastic_driver(): void
    {
        /** @var EngineManager $manager */
        $manager = $this->app->make(EngineManager::class);

        $this->app['config']->set('scout.driver', 'elastic');

        $engine = $manager->engine('elastic');

        $this->assertInstanceOf(Engine::class, $engine);
    }

    #[Test]
    public function it_extends_engine_manager_with_null_driver(): void
    {
        /** @var EngineManager $manager */
        $manager = $this->app->make(EngineManager::class);

        $engine = $manager->engine('null');

        $this->assertInstanceOf(NullEngine::class, $engine);
    }

    #[Test]
    public function it_registers_multiple_connections(): void
    {
        $this->app['config']->set('elastic.client.connections.secondary.hosts', ['localhost:9201']);

        // Force re-registration
        $provider = new ServiceProvider($this->app);
        $provider->register();

        $this->assertTrue($this->app->bound('elastic.client.connection.secondary'));
    }

    #[Test]
    public function it_publishes_config_files(): void
    {
        $this->artisan('vendor:publish', [
            '--tag' => 'elastic-client-config',
        ])->assertSuccessful();

        $this->artisan('vendor:publish', [
            '--tag' => 'elastic-scout-config',
        ])->assertSuccessful();

        $this->assertFileExists($this->app->configPath('elastic.client.php'));
        $this->assertFileExists($this->app->configPath('elastic.scout.php'));
    }

    #[Test]
    public function it_respects_refresh_documents_config(): void
    {
        $this->app['config']->set('elastic.scout.refresh_documents', true);

        // Force re-registration
        $this->app->forgetInstance(Engine::class);

        $provider = new ServiceProvider($this->app);
        $provider->register();

        $engine = $this->app->make(Engine::class);

        $refreshDocuments = new ReflectionProperty($engine, 'refreshDocuments');

        $this->assertTrue((bool) $refreshDocuments->getValue($engine));
    }

    #[Test]
    public function it_replaces_default_remove_from_search_job_for_elastic_driver(): void
    {
        Scout::removeFromSearchUsing(DefaultRemoveFromSearch::class);

        $provider = new ServiceProvider($this->app);
        $provider->boot();

        $this->assertSame(ElasticRemoveFromSearch::class, Scout::$removeFromSearchJob);
    }

    #[Test]
    public function it_does_not_replace_custom_remove_from_search_job(): void
    {
        Scout::removeFromSearchUsing(self::class);

        $provider = new ServiceProvider($this->app);
        $provider->boot();

        $this->assertSame(self::class, Scout::$removeFromSearchJob);
    }

    #[Test]
    public function it_does_not_replace_job_when_driver_is_not_elastic(): void
    {
        $this->app['config']->set('scout.driver', 'algolia');
        Scout::removeFromSearchUsing(DefaultRemoveFromSearch::class);

        $provider = new ServiceProvider($this->app);
        $provider->boot();

        $this->assertSame(DefaultRemoveFromSearch::class, Scout::$removeFromSearchJob);
    }
}
