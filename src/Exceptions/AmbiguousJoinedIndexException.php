<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Exceptions;

use InvalidArgumentException;

final class AmbiguousJoinedIndexException extends InvalidArgumentException
{
    public function __construct(string $indexName, string $registeredModelClass, string $joiningModelClass)
    {
        parent::__construct(sprintf(
            'Cannot join model %s because index %s is already mapped to model %s. A joined index must map to a single model class.',
            $joiningModelClass,
            $indexName,
            $registeredModelClass,
        ));
    }
}
