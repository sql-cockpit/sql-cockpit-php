<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * Your plan.
 */
final class AccountPlan extends Model
{
    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: description.
     */
    public readonly ?string $description;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->name = Data::string($data, 'name');
        $this->description = Data::nullableString($data, 'description');
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
