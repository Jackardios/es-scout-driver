<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\RegexpQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegexpQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_regexp_query(): void
    {
        $query = new RegexpQuery('name', 'joh.*');

        $this->assertSame([
            'regexp' => ['name' => ['value' => 'joh.*']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_regexp_query_with_all_options(): void
    {
        $query = (new RegexpQuery('name', 'joh.*'))
            ->flags('ALL')
            ->maxDeterminizedStates(10000)
            ->rewrite('constant_score')
            ->caseInsensitive(true)
            ->boost(2.0);

        $this->assertSame([
            'regexp' => ['name' => [
                'value' => 'joh.*',
                'flags' => 'ALL',
                'max_determinized_states' => 10000,
                'rewrite' => 'constant_score',
                'case_insensitive' => true,
                'boost' => 2.0,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_regexp_query_with_boost(): void
    {
        $query = (new RegexpQuery('name', 'joh.*'))
            ->boost(1.5);

        $this->assertSame([
            'regexp' => ['name' => [
                'value' => 'joh.*',
                'boost' => 1.5,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new RegexpQuery('name', 'joh.*');

        $this->assertSame($query, $query->flags('ALL'));
        $this->assertSame($query, $query->maxDeterminizedStates(10000));
        $this->assertSame($query, $query->rewrite('constant_score'));
        $this->assertSame($query, $query->caseInsensitive(true));
        $this->assertSame($query, $query->boost(2.0));
    }
}
