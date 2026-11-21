<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A hosted or self-hosted SQL Cockpit Server instance.
 */
final class Instance extends Model
{
    /**
     * JSON: id.
     */
    public readonly string $id;

    /**
     * JSON: tenant_id.
     */
    public readonly string $tenantId;

    /**
     * JSON: tenant_name.
     */
    public readonly ?string $tenantName;

    /**
     * JSON: hostname.
     */
    public readonly ?string $hostname;

    /**
     * JSON: public_url.
     */
    public readonly ?string $publicUrl;

    /**
     * JSON: hosting_mode.
     */
    public readonly string $hostingMode;

    /**
     * JSON: status.
     */
    public readonly string $status;

    /**
     * JSON: revision.
     */
    public readonly int $revision;

    /**
     * JSON: applied_revision.
     */
    public readonly ?int $appliedRevision;

    /**
     * JSON: last_seen_at.
     */
    public readonly ?string $lastSeenAt;

    /**
     * JSON: created_at.
     */
    public readonly string $createdAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->tenantId = Data::string($data, 'tenant_id');
        $this->tenantName = Data::nullableString($data, 'tenant_name');
        $this->hostname = Data::nullableString($data, 'hostname');
        $this->publicUrl = Data::nullableString($data, 'public_url');
        $this->hostingMode = Data::string($data, 'hosting_mode');
        $this->status = Data::string($data, 'status');
        $this->revision = Data::int($data, 'revision');
        $this->appliedRevision = Data::nullableInt($data, 'applied_revision');
        $this->lastSeenAt = Data::nullableString($data, 'last_seen_at');
        $this->createdAt = Data::string($data, 'created_at');
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
