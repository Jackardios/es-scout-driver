<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\PrefixQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrefixQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_prefix_query(): void
    {
        $query = new PrefixQuery('username', 'joh');

        $this->assertSame([
            'prefix' => ['username' => ['value' => 'joh']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_prefix_query_with_all_options(): void
    {
        $query = (new PrefixQuery('username', 'joh'))
            ->rewrite('constant_score')
            ->caseInsensitive(true);

        $this->assertSame([
            'prefix' => ['username' => [
                'value' => 'joh',
                'rewrite' => 'constant_score',
                'case_insensitive' => true,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new PrefixQuery('username', 'joh');

        $this->assertSame($query, $query->rewrite('constant_score'));
        $this->assertSame($query, $query->caseInsensitive(true));
    }
}
