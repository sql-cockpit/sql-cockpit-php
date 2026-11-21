<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * One allowance of your plan and how much of it is used.
 */
final class Allowance extends Model
{
    /**
     * JSON: key.
     */
    public readonly string $key;

    /**
     * JSON: label.
     */
    public readonly string $label;

    /**
     * JSON: metered.
     */
    public readonly bool $metered;

    /**
     * JSON: used.
     */
    public readonly int $used;

    /**
     * JSON: limit.
     */
    public readonly ?int $limit;

    /**
     * JSON: unlimited.
     */
    public readonly bool $unlimited;

    /**
     * JSON: included.
     */
    public readonly bool $included;

    /**
     * JSON: remaining.
     */
    public readonly ?int $remaining;

    /**
     * JSON: at_limit.
     */
    public readonly bool $atLimit;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->key = Data::string($data, 'key');
        $this->label = Data::string($data, 'label');
        $this->metered = Data::bool($data, 'metered');
        $this->used = Data::int($data, 'used');
        $this->limit = Data::nullableInt($data, 'limit');
        $this->unlimited = Data::bool($data, 'unlimited');
        $this->included = Data::bool($data, 'included');
        $this->remaining = Data::nullableInt($data, 'remaining');
        $this->atLimit = Data::bool($data, 'at_limit');
    }

    /**
     * Builds the object from the API's JSON (snake_case keys).
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
