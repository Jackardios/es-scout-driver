<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Engine;

trait ExtractsHitMetadata
{
    /**
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    protected function extractHitMetadata(array $hit): array
    {
        $metadata = [];

        foreach ($hit as $key => $value) {
            if (str_starts_with($key, '_') && $key !== '_source') {
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }
}
