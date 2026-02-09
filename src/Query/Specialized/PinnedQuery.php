<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Specialized;

use Jackardios\EsScoutDriver\Query\Concerns\HasBoost;
use Jackardios\EsScoutDriver\Query\QueryInterface;

final class PinnedQuery implements QueryInterface
{
    use HasBoost;

    /** @var array<int, string>|null */
    private ?array $ids = null;
    /** @var array<int, array{_index: string, _id: string}>|null */
    private ?array $docs = null;

    public function __construct(
        private QueryInterface|array $organic,
    ) {}

    /** @param array<int, string> $ids */
    public function ids(array $ids): self
    {
        $this->ids = $ids;
        return $this;
    }

    /** @param array<int, array{_index: string, _id: string}> $docs */
    public function docs(array $docs): self
    {
        $this->docs = $docs;
        return $this;
    }

    public function doc(string $index, string $id): self
    {
        $this->docs ??= [];
        $this->docs[] = ['_index' => $index, '_id' => $id];
        return $this;
    }

    public function toArray(): array
    {
        $params = [
            'organic' => $this->organic instanceof QueryInterface
                ? $this->organic->toArray()
                : $this->organic,
        ];

        if ($this->ids !== null) {
            $params['ids'] = $this->ids;
        }

        if ($this->docs !== null) {
            $params['docs'] = $this->docs;
        }

        $this->applyBoost($params);

        return ['pinned' => $params];
    }

    public function __clone(): void
    {
        if ($this->organic instanceof QueryInterface) {
            $this->organic = clone $this->organic;
        }
    }
}
