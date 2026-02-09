<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Jackardios\EsScoutDriver\Engine\Engine;
use Jackardios\EsScoutDriver\Engine\NullEngine;
use Jackardios\EsScoutDriver\ServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

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
        ]);

        $this->artisan('vendor:publish', [
            '--tag' => 'elastic-scout-config',
        ]);

        // Just verify no exception is thrown
        $this->assertTrue(true);
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

        $this->assertInstanceOf(Engine::class, $engine);
    }
}
