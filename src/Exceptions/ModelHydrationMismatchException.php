<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

final class ModelHydrationMismatchException extends SearchException
{
    /**
     * @param array<int, array{index: string, id: string}> $missingDocuments
     */
    public function __construct(
        public readonly int $totalHits,
        public readonly int $resolvedModels,
        public readonly int $missingModels,
        public readonly array $missingDocuments = [],
    ) {
        parent::__construct(sprintf(
            'Model hydration mismatch: %d of %d hit(s) could not be hydrated. '
            . 'Avoid filtering in modifyQuery()/modifyModels(), or set elastic.scout.model_hydration_mismatch=ignore|log.',
            $this->missingModels,
            $this->totalHits,
        ));
    }
}
