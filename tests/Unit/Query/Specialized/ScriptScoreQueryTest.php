<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Specialized\ScriptScoreQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScriptScoreQueryTest extends TestCase
{
    #[Test]
    public function it_builds_script_score_query_with_query_interface(): void
    {
        $innerQuery = new MatchAllQuery();
        $query = new ScriptScoreQuery($innerQuery, ['source' => "_score * doc['popularity'].value"]);

        $result = $query->toArray();
        $this->assertSame('script_score', array_key_first($result));

        $scriptScore = $result['script_score'];
        $this->assertArrayHasKey('match_all', $scriptScore['query']);
        $this->assertSame(['source' => "_score * doc['popularity'].value"], $scriptScore['script']);
    }

    #[Test]
    public function it_builds_script_score_query_with_array_query(): void
    {
        $query = new ScriptScoreQuery(
            ['match' => ['title' => 'search']],
            ['source' => '_score * 2']
        );

        $this->assertSame([
            'script_score' => [
                'query' => ['match' => ['title' => 'search']],
                'script' => ['source' => '_score * 2'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_script_score_query_with_all_options(): void
    {
        $query = (new ScriptScoreQuery(
            ['match_all' => []],
            ['source' => '_score * params.factor', 'params' => ['factor' => 2]]
        ))
            ->minScore(5.0)
            ->boost(1.5);

        $this->assertSame([
            'script_score' => [
                'query' => ['match_all' => []],
                'script' => ['source' => '_score * params.factor', 'params' => ['factor' => 2]],
                'min_score' => 5.0,
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new ScriptScoreQuery(['match_all' => []], ['source' => '_score']);

        $this->assertSame($query, $query->minScore(5.0));
        $this->assertSame($query, $query->boost(1.5));
    }
}
