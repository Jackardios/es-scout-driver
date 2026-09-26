<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExistsQueryTest extends TestCase
{
    #[Test]
    public function it_builds_exists_query(): void
    {
        $query = new ExistsQuery('email');

        $this->assertSame([
            'exists' => ['field' => 'email'],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_exists_query_with_boost(): void
    {
        $query = (new ExistsQuery('email'))->boost(1.5);

        $this->assertSame([
            'exists' => ['field' => 'email', 'boost' => 1.5],
        ], $query->toArray());
    }
}
