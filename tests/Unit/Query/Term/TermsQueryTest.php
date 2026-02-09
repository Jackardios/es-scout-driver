<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Term;

use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TermsQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_terms_query(): void
    {
        $query = new TermsQuery('status', ['active', 'pending']);

        $this->assertSame([
            'terms' => ['status' => ['active', 'pending']],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_terms_query_with_boost(): void
    {
        $query = (new TermsQuery('status', ['active']))->boost(1.5);

        $this->assertSame([
            'terms' => [
                'status' => ['active'],
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new TermsQuery('status', ['active']);

        $this->assertSame($query, $query->boost(1.5));
    }
}
