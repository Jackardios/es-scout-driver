<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Elastic\Elasticsearch\Exception\ClientResponseException;
use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AliasRegistryTest extends TestCase
{
    #[Test]
    public function resolve_returns_registered_indices_as_is_without_requests(): void
    {
        $http = new FakeHttpClient();
        $registry = new AliasRegistry($http->client());

        $registry->registerIndex('books');
        $registry->registerIndex('authors');

        $this->assertSame('books', $registry->resolve('books'));
        $this->assertSame('authors', $registry->resolve('authors'));
        $this->assertSame([], $http->requests);
    }

    #[Test]
    public function resolve_returns_the_index_name_without_a_client(): void
    {
        $registry = new AliasRegistry();
        $registry->registerIndex('books');

        $this->assertSame('books_v1', $registry->resolve('books_v1'));
    }

    #[Test]
    public function resolve_maps_the_concrete_indices_of_each_registered_name_once(): void
    {
        $http = new FakeHttpClient([
            [200, ['books_v2' => self::settings()]],
            [200, ['logs-2024' => self::settings(), 'logs-2025' => self::settings()]],
        ]);
        $registry = new AliasRegistry($http->client());
        $registry->registerIndex('books');
        $registry->registerIndex('logs-*,-logs-old');

        $this->assertSame('books', $registry->resolve('books_v2'));
        $this->assertSame('logs-*,-logs-old', $registry->resolve('logs-2024'));
        $this->assertSame('logs-*,-logs-old', $registry->resolve('logs-2025'));
        $this->assertSame('unknown', $registry->resolve('unknown'));

        $this->assertCount(2, $http->requests);
        $this->assertSame('/books/_settings/index.uuid', $http->requests[0]->getUri()->getPath());
        $this->assertSame('/logs-%2A%2C-logs-old/_settings/index.uuid', $http->requests[1]->getUri()->getPath());
    }

    #[Test]
    public function resolve_fetches_only_indices_registered_after_the_last_fetch(): void
    {
        $http = new FakeHttpClient([
            [200, ['books_v2' => self::settings()]],
            [200, ['authors_v3' => self::settings()]],
        ]);
        $registry = new AliasRegistry($http->client());
        $registry->registerIndex('books');
        $this->assertSame('books', $registry->resolve('books_v2'));

        $registry->registerIndex('books');
        $registry->registerIndex('authors');

        $this->assertSame('authors', $registry->resolve('authors_v3'));
        $this->assertSame('books', $registry->resolve('books_v2'));
        $this->assertCount(2, $http->requests);
    }

    #[Test]
    public function resolve_ignores_a_missing_registered_index(): void
    {
        $http = new FakeHttpClient([
            [404, ['error' => ['type' => 'index_not_found_exception'], 'status' => 404]],
        ]);
        $registry = new AliasRegistry($http->client());
        $registry->registerIndex('books');

        $this->assertSame('books_v1', $registry->resolve('books_v1'));
        $this->assertSame('books_v1', $registry->resolve('books_v1'));
        $this->assertCount(1, $http->requests);
    }

    #[Test]
    public function resolve_rethrows_other_client_errors(): void
    {
        $http = new FakeHttpClient([
            [403, ['error' => ['type' => 'security_exception'], 'status' => 403]],
        ]);
        $registry = new AliasRegistry($http->client());
        $registry->registerIndex('books');

        $this->expectException(ClientResponseException::class);

        $registry->resolve('books_v1');
    }

    #[Test]
    public function the_registry_of_a_sole_index_resolves_every_index_to_it(): void
    {
        $registry = AliasRegistry::sole('books');

        $this->assertSame('books', $registry->resolve('books'));
        $this->assertSame('books', $registry->resolve('books_v2'));
    }

    /** @return array<string, mixed> */
    private static function settings(): array
    {
        return ['settings' => ['index' => ['uuid' => 'uuid']]];
    }
}
