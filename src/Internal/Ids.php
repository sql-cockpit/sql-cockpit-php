<?php

declare(strict_types=1);

namespace SqlCockpit\Internal;

use SqlCockpit\Exception\ValidationException;

/**
 * Checks ids before they go into a path or a filter, so nothing is sent when one is wrong.
 *
 * @internal
 */
final class Ids
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public static function uuid(string $value, string $name): string
    {
        if (preg_match(self::UUID_PATTERN, $value) !== 1) {
            throw new ValidationException(
                \sprintf('%s must be a UUID.', $name),
                'invalid_argument',
                0,
                [$name => ['Must be a UUID.']],
            );
        }

        return strtolower($value);
    }

    public static function optionalUuid(?string $value, string $name): ?string
    {
        return $value === null || $value === '' ? null : self::uuid($value, $name);
    }
}
