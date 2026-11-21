<?php

declare(strict_types=1);

namespace SqlCockpit\Internal;

/**
 * Reads typed values out of decoded JSON. A missing or mistyped value becomes the default
 * (an empty string, false, 0 or null), so a new or absent field never breaks a call.
 *
 * @internal
 */
final class Data
{
    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : (\is_int($value) || \is_float($value) ? (string) $value : '');
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) ? $value : (\is_int($value) || \is_float($value) ? (string) $value : null);
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return ($data[$key] ?? null) === true;
    }

    /**
     * @param array<mixed> $data
     */
    public static function int(array $data, string $key): int
    {
        return self::nullableInt($data, $key) ?? 0;
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (\is_int($value)) {
            return $value;
        }
        if (\is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (\is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    /**
     * An object (JSON object) value, or null.
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>|null
     */
    public static function object(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return \is_array($value) ? $value : null;
    }

    /**
     * A list of objects. Entries that are not objects are skipped.
     *
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (\is_array($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        return self::nullableStrings($data, $key) ?? [];
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>|null
     */
    public static function nullableStrings(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return null;
        }
        $list = [];
        foreach ($value as $item) {
            if (\is_string($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * A JSON object of strings, such as role_summaries.
     *
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    public static function stringMap(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!\is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $name => $item) {
            if (\is_string($item)) {
                $map[(string) $name] = $item;
            }
        }

        return $map;
    }
}
