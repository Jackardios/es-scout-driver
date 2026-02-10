<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Engine;

use Jackardios\EsScoutDriver\Engine\AliasRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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

    #[Test]
    public function register_index_invalidates_alias_cache_for_new_indices(): void
    {
        $registry = new AliasRegistry();
        $registry->registerIndex('books');

        $this->setPrivateProperty($registry, 'fetched', true);
        $this->setPrivateProperty($registry, 'lastFetchTime', time());
        $this->setPrivateProperty($registry, 'aliasMap', ['books_v1' => 'books']);

        $registry->registerIndex('authors');

        $this->assertFalse($this->getPrivateProperty($registry, 'fetched'));
        $this->assertNull($this->getPrivateProperty($registry, 'lastFetchTime'));
        $this->assertSame([], $this->getPrivateProperty($registry, 'aliasMap'));
    }

    #[Test]
    public function register_index_does_not_invalidate_cache_for_existing_index(): void
    {
        $registry = new AliasRegistry();
        $registry->registerIndex('books');

        $timestamp = time();
        $this->setPrivateProperty($registry, 'fetched', true);
        $this->setPrivateProperty($registry, 'lastFetchTime', $timestamp);
        $this->setPrivateProperty($registry, 'aliasMap', ['books_v1' => 'books']);

        $registry->registerIndex('books');

        $this->assertTrue($this->getPrivateProperty($registry, 'fetched'));
        $this->assertSame($timestamp, $this->getPrivateProperty($registry, 'lastFetchTime'));
        $this->assertSame(['books_v1' => 'books'], $this->getPrivateProperty($registry, 'aliasMap'));
    }

    private function setPrivateProperty(AliasRegistry $registry, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($registry, $property);
        $reflection->setValue($registry, $value);
    }

    private function getPrivateProperty(AliasRegistry $registry, string $property): mixed
    {
        $reflection = new ReflectionProperty($registry, $property);
        return $reflection->getValue($registry);
    }
}
