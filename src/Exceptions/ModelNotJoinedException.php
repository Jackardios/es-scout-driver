<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

class ModelNotJoinedException extends \InvalidArgumentException
{
    public function __construct(string $modelClass)
    {
        parent::__construct(sprintf('Model %s is not joined to the search.', $modelClass));
    }
}
