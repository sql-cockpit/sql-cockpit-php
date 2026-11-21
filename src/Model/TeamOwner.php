<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * The owner of a tenant.
 */
final class TeamOwner extends Model
{
    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: email.
     */
    public readonly string $email;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->name = Data::string($data, 'name');
        $this->email = Data::string($data, 'email');
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
