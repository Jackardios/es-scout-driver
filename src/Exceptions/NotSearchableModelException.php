<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

final class NotSearchableModelException extends \InvalidArgumentException
{
    public function __construct(string $modelClass, ?string $message = null)
    {
        parent::__construct($message ?? sprintf(
            'Class %s must be an Eloquent model using the Searchable trait.',
            $modelClass,
        ));
    }
}
