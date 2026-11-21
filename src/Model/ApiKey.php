<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * One of your portal API keys. Its value is never returned.
 */
final class ApiKey extends Model
{
    /**
     * JSON: id.
     */
    public readonly int $id;

    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: status. "active", "expired" or "revoked".
     */
    public readonly string $status;

    /**
     * JSON: ends_with. The last four characters of the key.
     */
    public readonly string $endsWith;

    /**
     * JSON: created_at.
     */
    public readonly ?string $createdAt;

    /**
     * JSON: last_used_at.
     */
    public readonly ?string $lastUsedAt;

    /**
     * JSON: expires_at.
     */
    public readonly ?string $expiresAt;

    /**
     * JSON: revoked_at.
     */
    public readonly ?string $revokedAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::int($data, 'id');
        $this->name = Data::string($data, 'name');
        $this->status = Data::string($data, 'status');
        $this->endsWith = Data::string($data, 'ends_with');
        $this->createdAt = Data::nullableString($data, 'created_at');
        $this->lastUsedAt = Data::nullableString($data, 'last_used_at');
        $this->expiresAt = Data::nullableString($data, 'expires_at');
        $this->revokedAt = Data::nullableString($data, 'revoked_at');
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
