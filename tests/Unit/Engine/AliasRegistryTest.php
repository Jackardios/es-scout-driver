<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AliasRegistryTest extends TestCase
{
    #[Test]
    public function it_can_be_constructed_with_null_client(): void
    {
        $registry = new AliasRegistry();

        $this->assertInstanceOf(AliasRegistry::class, $registry);
    }

    #[Test]
    public function it_can_be_constructed_with_custom_ttl(): void
    {
        $registry = new AliasRegistry(null, 600);

        $this->assertInstanceOf(AliasRegistry::class, $registry);
    }

    #[Test]
    public function register_index_registers_an_index(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');

        $this->assertSame('books', $registry->resolve('books'));
    }

    #[Test]
    public function resolve_returns_registered_index_as_is(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');
        $registry->registerIndex('authors');

        $this->assertSame('books', $registry->resolve('books'));
        $this->assertSame('authors', $registry->resolve('authors'));
    }

    #[Test]
    public function resolve_returns_fallback_for_unknown_indices(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');

        $this->assertSame('unknown-index', $registry->resolve('unknown-index'));
    }

    #[Test]
    public function resolve_returns_index_name_itself_when_not_registered_and_no_client(): void
    {
        $registry = new AliasRegistry();

        $this->assertSame('some-index', $registry->resolve('some-index'));
    }

    #[Test]
    public function invalidate_clears_the_cache(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');
        $this->assertSame('books', $registry->resolve('books'));

        $registry->invalidate();

        $registry->registerIndex('authors');
        $this->assertSame('authors', $registry->resolve('authors'));
        $this->assertSame('books', $registry->resolve('books'));
    }

    #[Test]
    public function multiple_indices_can_be_registered(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');
        $registry->registerIndex('authors');
        $registry->registerIndex('stores');

        $this->assertSame('books', $registry->resolve('books'));
        $this->assertSame('authors', $registry->resolve('authors'));
        $this->assertSame('stores', $registry->resolve('stores'));
    }

    #[Test]
    public function registering_same_index_twice_does_not_duplicate(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');
        $registry->registerIndex('books');

        $this->assertSame('books', $registry->resolve('books'));
    }

    #[Test]
    public function invalidate_allows_re_registration(): void
    {
        $registry = new AliasRegistry();

        $registry->registerIndex('books');
        $this->assertSame('books', $registry->resolve('books'));

        $registry->invalidate();

        $this->assertSame('books', $registry->resolve('books'));
    }
}
