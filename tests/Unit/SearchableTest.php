<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit;

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
}
