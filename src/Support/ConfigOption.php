<?php

declare(strict_types=1);

namespace Jackardios\EsScoutDriver\Support;

use InvalidArgumentException;

/** @internal */
final class ConfigOption
{
    /**
     * Read a config value that must be one of the given lowercase options; null means the default.
     *
     * @template TOption of string
     * @param non-empty-list<TOption> $options
     * @param TOption $default
     * @return TOption
     */
    public static function oneOf(string $key, array $options, string $default): string
    {
        $value = config($key) ?? $default;
        $option = is_string($value) ? strtolower(trim($value)) : $value;

        if (!in_array($option, $options, true)) {
            throw new InvalidArgumentException(sprintf(
                'Config [%s] must be one of [%s], got [%s].',
                $key,
                implode(', ', $options),
                is_scalar($value) ? (string) $value : get_debug_type($value),
            ));
        }

        return $option;
    }
}
