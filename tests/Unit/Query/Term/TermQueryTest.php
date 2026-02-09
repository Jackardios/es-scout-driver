<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_term_query(): void
    {
        $query = new TermQuery('status', 'active');

        $this->assertSame([
            'term' => ['status' => ['value' => 'active']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_term_query_with_all_options(): void
    {
        $query = (new TermQuery('status', 'active'))
            ->boost(1.5)
            ->caseInsensitive(true);

        $this->assertSame([
            'term' => ['status' => [
                'value' => 'active',
                'boost' => 1.5,
                'case_insensitive' => true,
            ]],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new TermQuery('status', 'active');

        $this->assertSame($query, $query->boost(1.5));
        $this->assertSame($query, $query->caseInsensitive(true));
    }
}
