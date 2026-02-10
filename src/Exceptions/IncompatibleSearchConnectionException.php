<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

use InvalidArgumentException;

final class IncompatibleSearchConnectionException extends InvalidArgumentException
{
    public function __construct(
        string $baseModelClass,
        string $baseConnection,
        string $joinedModelClass,
        string $joinedConnection,
    ) {
        parent::__construct(sprintf(
            'Cannot join model %s (connection: %s) to model %s (connection: %s). Joined models must use the same searchable connection.',
            $joinedModelClass,
            $joinedConnection,
            $baseModelClass,
            $baseConnection,
        ));
    }
}
