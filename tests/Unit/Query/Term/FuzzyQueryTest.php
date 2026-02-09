<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FuzzyQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_fuzzy_query(): void
    {
        $query = new FuzzyQuery('title', 'test');

        $this->assertSame([
            'fuzzy' => ['title' => ['value' => 'test']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_fuzzy_query_with_all_options(): void
    {
        $query = (new FuzzyQuery('title', 'test'))
            ->fuzziness('AUTO')
            ->maxExpansions(50)
            ->prefixLength(2)
            ->rewrite('constant_score')
            ->transpositions(true)
            ->boost(1.5);

        $this->assertSame([
            'fuzzy' => ['title' => [
                'value' => 'test',
                'fuzziness' => 'AUTO',
                'max_expansions' => 50,
                'prefix_length' => 2,
                'rewrite' => 'constant_score',
                'transpositions' => true,
                'boost' => 1.5,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_fuzzy_query_with_integer_fuzziness(): void
    {
        $query = (new FuzzyQuery('title', 'test'))->fuzziness(2);

        $this->assertSame([
            'fuzzy' => ['title' => [
                'value' => 'test',
                'fuzziness' => 2,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_fuzzy_query_with_boost(): void
    {
        $query = (new FuzzyQuery('title', 'test'))->boost(2.0);

        $this->assertSame([
            'fuzzy' => ['title' => [
                'value' => 'test',
                'boost' => 2.0,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new FuzzyQuery('title', 'test');

        $this->assertSame($query, $query->fuzziness('AUTO'));
        $this->assertSame($query, $query->maxExpansions(50));
        $this->assertSame($query, $query->prefixLength(2));
        $this->assertSame($query, $query->rewrite('constant_score'));
        $this->assertSame($query, $query->transpositions(true));
        $this->assertSame($query, $query->boost(1.5));
    }
}
