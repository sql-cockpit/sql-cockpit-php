<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A member of a tenant.
 */
final class Member extends Model
{
    /**
     * JSON: id. The membership id.
     */
    public readonly string $id;

    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: email.
     */
    public readonly string $email;

    /**
     * JSON: role. One of the Role values.
     */
    public readonly string $role;

    /**
     * JSON: role_label.
     */
    public readonly string $roleLabel;

    /**
     * JSON: status. "active" or "pending_password".
     */
    public readonly string $status;

    /**
     * JSON: joined_at.
     */
    public readonly ?string $joinedAt;

    /**
     * JSON: added_at.
     */
    public readonly string $addedAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->name = Data::string($data, 'name');
        $this->email = Data::string($data, 'email');
        $this->role = Data::string($data, 'role');
        $this->roleLabel = Data::string($data, 'role_label');
        $this->status = Data::string($data, 'status');
        $this->joinedAt = Data::nullableString($data, 'joined_at');
        $this->addedAt = Data::string($data, 'added_at');
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
