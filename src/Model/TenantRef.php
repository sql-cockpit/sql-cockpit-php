<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A tenant's id and name.
 */
final class TenantRef extends Model
{
    /**
     * JSON: id.
     */
    public readonly string $id;

    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->name = Data::string($data, 'name');
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
