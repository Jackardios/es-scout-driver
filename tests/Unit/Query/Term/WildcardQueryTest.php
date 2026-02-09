<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WildcardQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_wildcard_query(): void
    {
        $query = new WildcardQuery('name', 'jo*');

        $this->assertSame([
            'wildcard' => ['name' => ['value' => 'jo*']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_wildcard_query_with_all_options(): void
    {
        $query = (new WildcardQuery('name', 'jo*'))
            ->boost(1.5)
            ->rewrite('constant_score')
            ->caseInsensitive(true);

        $this->assertSame([
            'wildcard' => ['name' => [
                'value' => 'jo*',
                'boost' => 1.5,
                'rewrite' => 'constant_score',
                'case_insensitive' => true,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new WildcardQuery('name', 'jo*');

        $this->assertSame($query, $query->boost(1.5));
        $this->assertSame($query, $query->rewrite('constant_score'));
        $this->assertSame($query, $query->caseInsensitive(true));
    }
}
