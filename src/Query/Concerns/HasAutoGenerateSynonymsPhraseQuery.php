<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

trait HasAutoGenerateSynonymsPhraseQuery
{
    private ?bool $autoGenerateSynonymsPhraseQuery = null;

    public function autoGenerateSynonymsPhraseQuery(bool $autoGenerateSynonymsPhraseQuery = true): self
    {
        $this->autoGenerateSynonymsPhraseQuery = $autoGenerateSynonymsPhraseQuery;
        return $this;
    }

    protected function applyAutoGenerateSynonymsPhraseQuery(array &$params): void
    {
        if ($this->autoGenerateSynonymsPhraseQuery !== null) {
            $params['auto_generate_synonyms_phrase_query'] = $this->autoGenerateSynonymsPhraseQuery;
        }
    }
}
