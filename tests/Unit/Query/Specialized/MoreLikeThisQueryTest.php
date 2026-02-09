<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Tests\Unit\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MoreLikeThisQueryTest extends TestCase
{
    #[Test]
    public function it_builds_basic_more_like_this_query_with_string_like(): void
    {
        $query = new MoreLikeThisQuery(['title', 'body'], 'some text');

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => 'some text',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_array_like(): void
    {
        $like = [
            'some text',
            ['_index' => 'my_index', '_id' => '1'],
        ];
        $query = new MoreLikeThisQuery(['title'], $like);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => $like,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_all_options(): void
    {
        $query = (new MoreLikeThisQuery(['title', 'body'], 'some text'))
            ->minTermFreq(2)
            ->maxQueryTerms(25)
            ->minDocFreq(5)
            ->maxDocFreq(100)
            ->minWordLength(3)
            ->maxWordLength(20)
            ->analyzer('standard')
            ->boost(1.5);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title', 'body'],
                'like' => 'some text',
                'min_term_freq' => 2,
                'max_query_terms' => 25,
                'min_doc_freq' => 5,
                'max_doc_freq' => 100,
                'min_word_length' => 3,
                'max_word_length' => 20,
                'analyzer' => 'standard',
                'boost' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_unlike(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->unlike('excluded text');

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'unlike' => 'excluded text',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_stop_words(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->stopWords(['the', 'a', 'an']);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'stop_words' => ['the', 'a', 'an'],
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_include(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->include(true);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'include' => true,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_boost_terms(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->boostTerms(1.5);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'boost_terms' => 1.5,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_fail_on_unsupported_field(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->failOnUnsupportedField(false);

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'fail_on_unsupported_field' => false,
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_builds_more_like_this_query_with_minimum_should_match(): void
    {
        $query = (new MoreLikeThisQuery(['title'], 'some text'))
            ->minimumShouldMatch('75%');

        $this->assertSame([
            'more_like_this' => [
                'fields' => ['title'],
                'like' => 'some text',
                'minimum_should_match' => '75%',
            ],
        ], $query->toArray());
    }

    #[Test]
    public function it_returns_fluent_interface(): void
    {
        $query = new MoreLikeThisQuery(['title'], 'some text');

        $this->assertSame($query, $query->minTermFreq(2));
        $this->assertSame($query, $query->maxQueryTerms(25));
        $this->assertSame($query, $query->minDocFreq(5));
        $this->assertSame($query, $query->maxDocFreq(100));
        $this->assertSame($query, $query->minWordLength(3));
        $this->assertSame($query, $query->maxWordLength(20));
        $this->assertSame($query, $query->analyzer('standard'));
        $this->assertSame($query, $query->boost(1.5));
        $this->assertSame($query, $query->unlike('excluded'));
        $this->assertSame($query, $query->stopWords(['the']));
        $this->assertSame($query, $query->include(true));
        $this->assertSame($query, $query->boostTerms(1.5));
        $this->assertSame($query, $query->failOnUnsupportedField(false));
        $this->assertSame($query, $query->minimumShouldMatch('75%'));
    }
}
