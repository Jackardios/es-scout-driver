<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Query\Concerns;

use Jackardios\EsScoutDriver\Enums\ValidationMethod;

trait HasValidationMethod
{
    private ?string $validationMethod = null;

    public function validationMethod(ValidationMethod|string $validationMethod): self
    {
        $this->validationMethod = $validationMethod instanceof ValidationMethod ? $validationMethod->value : $validationMethod;
        return $this;
    }

    protected function applyValidationMethod(array &$params): void
    {
        if ($this->validationMethod !== null) {
            $params['validation_method'] = $this->validationMethod;
        }
    }
}
