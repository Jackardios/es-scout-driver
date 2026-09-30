<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit;

use Elastic\Elasticsearch\Client;
use Jackardios\EsScoutDriver\Engine\EngineInterface;
use Jackardios\EsScoutDriver\Exceptions\SearchException;
use Jackardios\EsScoutDriver\Searchable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SearchableTest extends TestCase
{
    #[Test]
    public function trait_exists(): void
    {
        $this->assertTrue(trait_exists(Searchable::class));
    }

    #[Test]
    public function trait_has_search_query_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('searchQuery'));
    }

    #[Test]
    public function trait_has_searchable_routing_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('searchableRouting'));
    }

    #[Test]
    public function trait_has_searchable_with_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('searchableWith'));
    }

    #[Test]
    public function trait_has_searchable_connection_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('searchableConnection'));
    }

    #[Test]
    public function trait_has_searchable_using_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('searchableUsing'));
    }

    #[Test]
    public function trait_has_open_point_in_time_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('openPointInTime'));
    }

    #[Test]
    public function trait_has_close_point_in_time_method(): void
    {
        $reflection = new ReflectionClass(Searchable::class);

        $this->assertTrue($reflection->hasMethod('closePointInTime'));
    }

    #[Test]
    public function trait_uses_base_searchable(): void
    {
        $reflection = new ReflectionClass(Searchable::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains(\Laravel\Scout\Searchable::class, $traits);
    }

    #[Test]
    public function searchable_using_throws_for_unsupported_engine(): void
    {
        $model = new class {
            use Searchable;

            public function baseSearchableUsing(): object
            {
                return new \stdClass();
            }
        };

        $this->expectException(SearchException::class);
        $this->expectExceptionMessage('does not support es-scout-driver features');

        $model->searchableUsing();
    }

    #[Test]
    public function searchable_using_applies_custom_connection(): void
    {
        $model = new class {
            use Searchable;

            public function baseSearchableUsing(): EngineInterface
            {
                return new SearchableTestEngine();
            }

            public function searchableConnection(): ?string
            {
                return 'analytics';
            }
        };

        $engine = $model->searchableUsing();

        $this->assertInstanceOf(SearchableTestEngine::class, $engine);
        $this->assertSame('analytics', $engine->resolvedConnection);
    }

    #[Test]
    public function searchable_using_keeps_the_default_connection_for_an_empty_name(): void
    {
        $model = new class {
            use Searchable;

            public function baseSearchableUsing(): EngineInterface
            {
                return new SearchableTestEngine();
            }

            public function searchableConnection(): ?string
            {
                return '';
            }
        };

        $this->assertNull($model->searchableUsing()->resolvedConnection);
    }

    #[Test]
    public function open_point_in_time_passes_the_index_keep_alive_routing_and_preference_to_the_engine(): void
    {
        $model = new class {
            use Searchable;

            public function baseSearchableUsing(): EngineInterface
            {
                return SearchableTestEngine::$shared ??= new SearchableTestEngine();
            }

            public function searchableAs(): string
            {
                return 'books';
            }
        };

        $this->assertSame('pit-id', $model::openPointInTime('5m', routing: 'user_1', preference: '_local'));
        $this->assertSame(['books', '5m', 'user_1', '_local'], SearchableTestEngine::$shared?->pointInTimeArguments);
    }
}

final class SearchableTestEngine implements EngineInterface
{
    public static ?self $shared = null;

    public ?string $resolvedConnection = null;

    /** @var list<string|null> */
    public array $pointInTimeArguments = [];

    public function update($models): void {}

    public function delete($models): void {}

    public function searchRaw(array $params): array
    {
        return [];
    }

    public function openPointInTime(
        string $indexName,
        ?string $keepAlive = null,
        ?string $routing = null,
        ?string $preference = null,
    ): string {
        $this->pointInTimeArguments = [$indexName, $keepAlive, $routing, $preference];

        return 'pit-id';
    }

    public function closePointInTime(string $pointInTimeId): void {}

    public function deleteByQueryRaw(array $params): array
    {
        return [];
    }

    public function updateByQueryRaw(array $params): array
    {
        return [];
    }

    public function connection(string $connection): static
    {
        $clone = clone $this;
        $clone->resolvedConnection = $connection;

        return $clone;
    }

    public function getClient(): ?Client
    {
        return null;
    }
}
