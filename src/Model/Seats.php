<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * Seats used and included in your plan.
 */
final class Seats extends Model
{
    /**
     * JSON: used.
     */
    public readonly int $used;

    /**
     * JSON: limit. Null when unlimited.
     */
    public readonly ?int $limit;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->used = Data::int($data, 'used');
        $this->limit = Data::nullableInt($data, 'limit');
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
