<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Search;

use Generator;
use Jackardios\EsScoutDriver\Search\SearchCursor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SearchCursorTest extends TestCase
{
    #[Test]
    public function it_implements_iterator_aggregate(): void
    {
        $reflection = new \ReflectionClass(SearchCursor::class);

        $this->assertTrue($reflection->implementsInterface(\IteratorAggregate::class));
    }

    #[Test]
    public function it_has_required_constructor_parameters(): void
    {
        $reflection = new \ReflectionClass(SearchCursor::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertSame('builder', $params[0]->getName());
        $this->assertSame('chunkSize', $params[1]->getName());
        $this->assertSame('keepAlive', $params[2]->getName());

        $this->assertSame(1000, $params[1]->getDefaultValue());
        $this->assertSame('5m', $params[2]->getDefaultValue());
    }
}
