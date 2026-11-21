<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Enum\AssignableRole;
use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Ids;
use SqlCockpit\Model\MemberResult;
use SqlCockpit\Model\RemovedResult;
use SqlCockpit\Model\SentResult;
use SqlCockpit\Model\Team;

/**
 * Who can work in each tenant, and with which role.
 */
final class TeamResource
{
    /**
     * @internal Use $client->team.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /tenants/{tenantId}/members: the whole team, with the owner, the members, the roles
     * you may assign and the seats your plan includes.
     */
    public function list(string $tenantId): Team
    {
        $path = '/tenants/' . Ids::uuid($tenantId, 'tenantId') . '/members';

        return Team::fromArray($this->requester->request('GET', $path));
    }

    /**
     * POST /tenants/{tenantId}/members: adds someone to the tenant. A person without a SQL Cockpit
     * account receives an email to set a password.
     *
     * @param string                $name  2 to 120 characters.
     * @param string                $email Up to 254 characters.
     * @param AssignableRole|string $role  admin, developer, operator or read-only.
     */
    public function add(string $tenantId, string $name, string $email, AssignableRole|string $role): MemberResult
    {
        $path = '/tenants/' . Ids::uuid($tenantId, 'tenantId') . '/members';
        $body = ['name' => $name, 'email' => $email, 'role' => self::role($role)];

        return MemberResult::fromArray($this->requester->request('POST', $path, [], $body));
    }

    /**
     * PATCH /tenants/{tenantId}/members/{membershipId}: gives a member another role.
     * Sends confirm: true, so only call it when you mean it.
     */
    public function changeRole(string $tenantId, string $membershipId, AssignableRole|string $role): MemberResult
    {
        $body = ['role' => self::role($role), 'confirm' => true];

        return MemberResult::fromArray($this->requester->request('PATCH', self::memberPath($tenantId, $membershipId), [], $body));
    }

    /**
     * DELETE /tenants/{tenantId}/members/{membershipId}: removes a member from the tenant. Their
     * SQL Cockpit account stays. Sends confirm: true, so only call it when you mean it.
     */
    public function remove(string $tenantId, string $membershipId): RemovedResult
    {
        return RemovedResult::fromArray(
            $this->requester->request('DELETE', self::memberPath($tenantId, $membershipId), [], ['confirm' => true])
        );
    }

    /**
     * POST /tenants/{tenantId}/members/{membershipId}/resend: sends the set-password email again
     * to a member whose status is pending_password.
     */
    public function resendInvite(string $tenantId, string $membershipId): SentResult
    {
        return SentResult::fromArray($this->requester->request('POST', self::memberPath($tenantId, $membershipId) . '/resend'));
    }

    private static function memberPath(string $tenantId, string $membershipId): string
    {
        return '/tenants/' . Ids::uuid($tenantId, 'tenantId') . '/members/' . Ids::uuid($membershipId, 'membershipId');
    }

    private static function role(AssignableRole|string $role): string
    {
        return $role instanceof AssignableRole ? $role->value : $role;
    }
}
