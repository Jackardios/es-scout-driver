<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

class NotSearchableModelException extends \InvalidArgumentException
{
    public function __construct(string $modelClass)
    {
        parent::__construct(sprintf('Class %s must be an Eloquent model using the Searchable trait.', $modelClass));
    }
}
