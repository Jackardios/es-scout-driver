<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Exceptions\InvalidQueryException;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KnnQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_knn_query(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2, 0.3], 10);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.1, 0.2, 0.3],
                'k' => 10,
                'num_candidates' => 100,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_uses_default_num_candidates_as_k_times_2_when_larger_than_100(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2, 0.3], 60);

        $result = $query->toArray();
        $this->assertSame(120, $result['knn']['num_candidates']);
    }

    #[Test]
    public function it_uses_default_num_candidates_as_100_when_k_times_2_is_smaller(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2, 0.3], 10);

        $result = $query->toArray();
        $this->assertSame(100, $result['knn']['num_candidates']);
    }

    #[Test]
    public function it_builds_knn_query_with_custom_num_candidates(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->numCandidates(200);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.1, 0.2, 0.3],
                'k' => 10,
                'num_candidates' => 200,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_knn_query_with_similarity(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->similarity(0.8);

        $result = $query->toArray();
        $this->assertSame(0.8, $result['knn']['similarity']);
    }

    #[Test]
    public function it_builds_knn_query_with_filter_query_interface(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->filter(new TermQuery('status', 'active'));

        $result = $query->toArray();
        $this->assertSame(['term' => ['status' => ['value' => 'active']]], $result['knn']['filter']);
    }

    #[Test]
    public function it_builds_knn_query_with_filter_array(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->filter(['term' => ['status' => 'published']]);

        $result = $query->toArray();
        $this->assertSame(['term' => ['status' => 'published']], $result['knn']['filter']);
    }

    #[Test]
    public function it_builds_knn_query_with_boost(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->boost(2.0);

        $result = $query->toArray();
        $this->assertSame(2.0, $result['knn']['boost']);
    }

    #[Test]
    public function it_builds_knn_query_with_inner_hits(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->innerHits(['name' => 'my_inner_hits', 'size' => 5]);

        $result = $query->toArray();
        $this->assertSame(['name' => 'my_inner_hits', 'size' => 5], $result['knn']['inner_hits']);
    }

    #[Test]
    public function it_builds_knn_query_with_empty_inner_hits(): void
    {
        $query = (new KnnQuery('embedding', [0.1, 0.2, 0.3], 10))
            ->innerHits();

        $result = $query->toArray();
        $this->assertEquals(new \stdClass(), $result['knn']['inner_hits']);
    }

    #[Test]
    public function it_builds_knn_query_with_all_options(): void
    {
        $query = (new KnnQuery('embedding', [0.5, 0.5], 20))
            ->numCandidates(150)
            ->similarity(0.9)
            ->filter(new TermQuery('category', 'tech'))
            ->innerHits(['size' => 3])
            ->boost(1.5);

        $this->assertSame([
            'knn' => [
                'field' => 'embedding',
                'query_vector' => [0.5, 0.5],
                'k' => 20,
                'num_candidates' => 150,
                'similarity' => 0.9,
                'filter' => ['term' => ['category' => ['value' => 'tech']]],
                'inner_hits' => ['size' => 3],
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new KnnQuery('embedding', [0.1, 0.2], 5);

        $this->assertSame($query, $query->numCandidates(100));
        $this->assertSame($query, $query->similarity(0.8));
        $this->assertSame($query, $query->filter(['term' => ['x' => 'y']]));
        $this->assertSame($query, $query->innerHits(['size' => 3]));
        $this->assertSame($query, $query->boost(1.0));
    }

    #[Test]
    public function it_throws_exception_for_k_equal_to_zero_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires k to be greater than 0');

        new KnnQuery('embedding', [0.1, 0.2, 0.3], 0);
    }

    #[Test]
    public function it_throws_exception_for_negative_k_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires k to be greater than 0');

        new KnnQuery('embedding', [0.1, 0.2, 0.3], -5);
    }

    #[Test]
    public function it_throws_exception_for_empty_query_vector_in_constructor(): void
    {
        $this->expectException(InvalidQueryException::class);
        $this->expectExceptionMessage('KnnQuery requires a non-empty query vector');

        new KnnQuery('embedding', [], 10);
    }
}
