<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Compound;

use Jackardios\EsScoutDriver\Query\Compound\ConstantScoreQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConstantScoreQueryTest extends TestCase
{
    #[Test]
    public function it_builds_constant_score_query_with_query_interface(): void
    {
        $query = new ConstantScoreQuery(new TermQuery('status', 'active'));

        $this->assertSame([
            'constant_score' => [
                'filter' => ['term' => ['status' => ['value' => 'active']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_constant_score_query_with_array_filter(): void
    {
        $query = new ConstantScoreQuery(['term' => ['status' => 'published']]);

        $this->assertSame([
            'constant_score' => [
                'filter' => ['term' => ['status' => 'published']],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_constant_score_query_with_closure(): void
    {
        $query = new ConstantScoreQuery(fn() => new TermQuery('status', 'active'));

        $this->assertSame([
            'constant_score' => [
                'filter' => ['term' => ['status' => ['value' => 'active']]],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_constant_score_query_with_boost(): void
    {
        $query = (new ConstantScoreQuery(new TermQuery('status', 'active')))
            ->boost(1.5);

        $this->assertSame([
            'constant_score' => [
                'filter' => ['term' => ['status' => ['value' => 'active']]],
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new ConstantScoreQuery(new TermQuery('status', 'active'));

        $this->assertSame($query, $query->boost(1.0));
    }
}
