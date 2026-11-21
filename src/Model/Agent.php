<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A SQL Cockpit Agent paired with one of your tenants.
 */
final class Agent extends Model
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
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: hostname.
     */
    public readonly ?string $hostname;

    /**
     * JSON: status. One of the AgentStatus values.
     */
    public readonly string $status;

    /**
     * JSON: connection.
     */
    public readonly string $connection;

    /**
     * JSON: update_channel.
     */
    public readonly ?string $updateChannel;

    /**
     * JSON: desired_role.
     */
    public readonly ?string $desiredRole;

    /**
     * JSON: last_seen_at.
     */
    public readonly ?string $lastSeenAt;

    /**
     * JSON: registered_at.
     */
    public readonly ?string $registeredAt;

    /**
     * JSON: revoked_at.
     */
    public readonly ?string $revokedAt;

    /**
     * JSON: latest_command.
     */
    public readonly ?AgentCommand $latestCommand;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->tenantId = Data::string($data, 'tenant_id');
        $this->tenantName = Data::nullableString($data, 'tenant_name');
        $this->name = Data::string($data, 'name');
        $this->hostname = Data::nullableString($data, 'hostname');
        $this->status = Data::string($data, 'status');
        $this->connection = Data::string($data, 'connection');
        $this->updateChannel = Data::nullableString($data, 'update_channel');
        $this->desiredRole = Data::nullableString($data, 'desired_role');
        $this->lastSeenAt = Data::nullableString($data, 'last_seen_at');
        $this->registeredAt = Data::nullableString($data, 'registered_at');
        $this->revokedAt = Data::nullableString($data, 'revoked_at');
        $this->latestCommand = ($v = Data::object($data, 'latest_command')) === null ? null : AgentCommand::fromArray($v);
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
