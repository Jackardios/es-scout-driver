<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Illuminate\Container\Container;
use Jackardios\EsScoutDriver\Engine\ConnectionOperationRouter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConnectionOperationRouterTest extends TestCase
{
    private Container $container;
    private Container $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->container = new Container();
        Container::setInstance($this->container);
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    #[Test]
    public function normalize_returns_default_for_empty_connection(): void
    {
        $router = new ConnectionOperationRouter();

        $this->assertSame(ConnectionOperationRouter::DEFAULT_CONNECTION, $router->normalize(null));
        $this->assertSame(ConnectionOperationRouter::DEFAULT_CONNECTION, $router->normalize(''));
        $this->assertSame('analytics', $router->normalize('analytics'));
    }

    #[Test]
    public function group_by_connection_groups_items_with_default_fallback(): void
    {
        $router = new ConnectionOperationRouter();

        $items = [
            ['id' => '1', 'connection' => null],
            ['id' => '2', 'connection' => 'analytics'],
            ['id' => '3', 'connection' => 'analytics'],
            ['id' => '4', 'connection' => 'archive'],
        ];

        $grouped = $router->groupByConnection(
            $items,
            static fn(array $item): ?string => is_string($item['connection'] ?? null) ? $item['connection'] : null,
        );

        $this->assertArrayHasKey(ConnectionOperationRouter::DEFAULT_CONNECTION, $grouped);
        $this->assertArrayHasKey('analytics', $grouped);
        $this->assertArrayHasKey('archive', $grouped);
        $this->assertCount(1, $grouped[ConnectionOperationRouter::DEFAULT_CONNECTION]);
        $this->assertCount(2, $grouped['analytics']);
        $this->assertCount(1, $grouped['archive']);
    }

    #[Test]
    public function resolve_client_for_default_connection_returns_default_client(): void
    {
        $router = new ConnectionOperationRouter();
        $defaultClient = new \stdClass();

        $resolved = $router->resolveClientForConnection(ConnectionOperationRouter::DEFAULT_CONNECTION, $defaultClient);

        $this->assertSame($defaultClient, $resolved);
    }

    #[Test]
    public function resolve_client_for_engine_default_connection_name_returns_default_client(): void
    {
        $router = new ConnectionOperationRouter();
        $defaultClient = new \stdClass();

        $resolved = $router->resolveClientForConnection('secondary', $defaultClient, 'secondary');

        $this->assertSame($defaultClient, $resolved);
    }

    #[Test]
    public function resolve_client_for_named_connection_uses_container_binding(): void
    {
        $router = new ConnectionOperationRouter();
        $defaultClient = new \stdClass();
        $analyticsClient = new \stdClass();
        $this->container->instance('elastic.client.connection.analytics', $analyticsClient);

        $resolved = $router->resolveClientForConnection('analytics', $defaultClient);

        $this->assertSame($analyticsClient, $resolved);
    }
}
