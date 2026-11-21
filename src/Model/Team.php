<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A tenant's team: GET /tenants/{tenantId}/members.
 */
final class Team extends Model
{
    /**
     * JSON: tenant.
     */
    public readonly TenantRef $tenant;

    /**
     * JSON: owner.
     */
    public readonly TeamOwner $owner;

    /**
     * JSON: members.
     *
     * @var list<Member>
     */
    public readonly array $members;

    /**
     * JSON: you_can_manage.
     */
    public readonly bool $youCanManage;

    /**
     * JSON: assignable_roles. The roles you may assign, or null when you cannot manage the team.
     *
     * @var list<string>|null
     */
    public readonly ?array $assignableRoles;

    /**
     * JSON: role_summaries. A short description of each role, keyed by role.
     *
     * @var array<string, string>
     */
    public readonly array $roleSummaries;

    /**
     * JSON: seats. Null when you cannot manage the team.
     */
    public readonly ?Seats $seats;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->tenant = TenantRef::fromArray(Data::object($data, 'tenant') ?? []);
        $this->owner = TeamOwner::fromArray(Data::object($data, 'owner') ?? []);
        $this->members = array_map(static fn (array $item): Member => Member::fromArray($item), Data::objects($data, 'members'));
        $this->youCanManage = Data::bool($data, 'you_can_manage');
        $this->assignableRoles = Data::nullableStrings($data, 'assignable_roles');
        $this->roleSummaries = Data::stringMap($data, 'role_summaries');
        $this->seats = ($v = Data::object($data, 'seats')) === null ? null : Seats::fromArray($v);
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
