<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * The current billing period.
 */
final class UsagePeriod extends Model
{
    /**
     * JSON: start.
     */
    public readonly string $start;

    /**
     * JSON: end.
     */
    public readonly string $end;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->start = Data::string($data, 'start');
        $this->end = Data::string($data, 'end');
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
