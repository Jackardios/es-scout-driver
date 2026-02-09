<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Term\IdsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdsQueryTest extends TestCase
{
    #[Test]
    public function it_builds_ids_query(): void
    {
        $query = new IdsQuery(['1', '2', '3']);

        $this->assertSame([
            'ids' => ['values' => ['1', '2', '3']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_ids_query_with_single_value(): void
    {
        $query = new IdsQuery(['42']);

        $this->assertSame([
            'ids' => ['values' => ['42']],
        ], $query->toArray());
    }

    #[Test]
    public function it_throws_on_empty_values(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('IdsQuery requires at least one value');

        $query = new IdsQuery([]);
        $query->toArray();
    }
}
