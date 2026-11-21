<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * What team->remove() returns.
 */
final class RemovedResult extends Model
{
    /**
     * JSON: removed.
     */
    public readonly bool $removed;

    /**
     * JSON: email.
     */
    public readonly string $email;

    /**
     * JSON: tenant_id.
     */
    public readonly string $tenantId;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->removed = Data::bool($data, 'removed');
        $this->email = Data::string($data, 'email');
        $this->tenantId = Data::string($data, 'tenant_id');
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
