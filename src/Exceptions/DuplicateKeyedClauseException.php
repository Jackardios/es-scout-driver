<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

use InvalidArgumentException;

final class DuplicateKeyedClauseException extends InvalidArgumentException
{
    public function __construct(string $section, string $key)
    {
        parent::__construct(sprintf(
            'Clause with key "%s" already exists in %s section',
            $key,
            $section,
        ));
    }
}
