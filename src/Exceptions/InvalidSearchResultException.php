<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

final class InvalidSearchResultException extends SearchException
{
    public static function missingTotalHits(): self
    {
        return new self(
            'Search result does not contain the total hits number. '
            . 'Please make sure that total hits are tracked.'
        );
    }
}
