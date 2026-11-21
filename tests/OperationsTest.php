<?php

declare(strict_types=1);

namespace SqlCockpit\Tests;

use SqlCockpit\Enum\AgentStatus;
use SqlCockpit\Enum\AssignableRole;
use SqlCockpit\Enum\TicketStatus;
use SqlCockpit\Model\Account;
use SqlCockpit\Model\Ticket;
use SqlCockpit\Tests\Support\ClientTestCase;

final class OperationsTest extends ClientTestCase
{
    public function testAccountGet(): void
    {
        $data = [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'email_verified' => true,
            'billing_account' => true,
            'team_member' => false,
            'plan' => ['name' => 'Team', 'description' => null],
            'subscription' => ['status' => 'active', 'cycle' => 'month', 'started_at' => '2026-01-01T00:00:00Z', 'trial_ends_at' => null, 'ends_at' => null],
            'connected_via' => ['type' => 'api_key', 'name' => 'ci', 'scopes' => ['portal:read', 'team:write'], 'expires_at' => null],
            'api_key_name' => 'ci',
            'portal_url' => 'https://portal.sql-cockpit.com',
            'billing_note' => 'Billing stays in the portal.',
        ];
        $account = $this->client([self::ok($data)])->account->get();

        $this->assertRequest('GET', self::BASE . '/account');
        self::assertInstanceOf(Account::class, $account);
        self::assertSame('Ada Lovelace', $account->name);
        self::assertSame('ada@example.com', $account->email);
        self::assertTrue($account->emailVerified);
        self::assertTrue($account->billingAccount);
        self::assertFalse($account->teamMember);
        self::assertSame('Team', $account->plan?->name);
        self::assertNull($account->plan->description);
        self::assertSame('month', $account->subscription?->cycle);
        self::assertSame('2026-01-01T00:00:00Z', $account->subscription->startedAt);
        self::assertSame(['portal:read', 'team:write'], $account->connectedVia?->scopes);
        self::assertSame('ci', $account->apiKeyName);
        self::assertSame('https://portal.sql-cockpit.com', $account->portalUrl);
        self::assertSame($data, $account->toArray());
        self::assertSame(json_encode($data), json_encode($account));
    }

    public function testAccountWithoutPlan(): void
    {
        $account = $this->client([self::ok(['name' => 'Sam', 'email' => 's@example.com', 'plan' => null, 'subscription' => null])])->account->get();

        self::assertNull($account->plan);
        self::assertNull($account->subscription);
        self::assertNull($account->connectedVia);
    }

    public function testAccountUsage(): void
    {
        $data = [
            'billing_account' => true,
            'plan' => 'Team',
            'period' => ['start' => '2026-11-01T00:00:00Z', 'end' => '2026-12-01T00:00:00Z'],
            'allowances' => [
                ['key' => 'agents', 'label' => 'Agents', 'metered' => false, 'used' => 3, 'limit' => 10, 'unlimited' => false, 'included' => true, 'remaining' => 7, 'at_limit' => false],
                ['key' => 'seats', 'label' => 'Seats', 'metered' => false, 'used' => 2, 'limit' => null, 'unlimited' => true, 'included' => true, 'remaining' => null, 'at_limit' => false],
            ],
        ];
        $usage = $this->client([self::ok($data)])->account->usage();

        $this->assertRequest('GET', self::BASE . '/usage');
        self::assertTrue($usage->billingAccount);
        self::assertFalse($usage->teamMemberOnly);
        self::assertNull($usage->message);
        self::assertSame('Team', $usage->plan);
        self::assertSame('2026-12-01T00:00:00Z', $usage->period?->end);
        self::assertCount(2, $usage->allowances);
        self::assertSame(7, $usage->allowances[0]->remaining);
        self::assertNull($usage->allowances[1]->limit);
        self::assertTrue($usage->allowances[1]->unlimited);
    }

    public function testApiKeysList(): void
    {
        $data = ['keys' => [[
            'id' => 7, 'name' => 'ci-pipeline', 'status' => 'active', 'ends_with' => 'mnop',
            'created_at' => '2026-11-01T00:00:00Z', 'last_used_at' => null, 'expires_at' => '2026-11-08T00:00:00Z', 'revoked_at' => null,
        ]]];
        $keys = $this->client([self::ok($data)])->apiKeys->list();

        $this->assertRequest('GET', self::BASE . '/api-keys');
        self::assertCount(1, $keys);
        self::assertSame(7, $keys[0]->id);
        self::assertSame('ci-pipeline', $keys[0]->name);
        self::assertSame('mnop', $keys[0]->endsWith);
        self::assertNull($keys[0]->lastUsedAt);
    }

    public function testTenantsList(): void
    {
        $data = ['tenants' => [[
            'id' => self::TENANT, 'name' => 'Acme', 'slug' => 'acme', 'subdomain' => 'acme', 'domain' => null,
            'status' => 'active', 'your_role' => 'owner', 'is_owner' => true, 'abilities' => ['team.manage'],
            'agents_count' => 2, 'created_at' => '2026-01-01T00:00:00Z',
        ]]];
        $tenants = $this->client([self::ok($data)])->tenants->list();

        $this->assertRequest('GET', self::BASE . '/tenants');
        self::assertCount(1, $tenants);
        self::assertSame(self::TENANT, $tenants[0]->id);
        self::assertSame('owner', $tenants[0]->yourRole);
        self::assertTrue($tenants[0]->isOwner);
        self::assertSame(['team.manage'], $tenants[0]->abilities);
        self::assertSame(2, $tenants[0]->agentsCount);
        self::assertNull($tenants[0]->domain);
    }

    public function testTeamListReturnsTheWholeTeam(): void
    {
        $data = [
            'tenant' => ['id' => self::TENANT, 'name' => 'Acme'],
            'owner' => ['name' => 'Ada', 'email' => 'ada@example.com'],
            'members' => [[
                'id' => self::MEMBER, 'name' => 'Sam', 'email' => 'sam@example.com', 'role' => 'developer',
                'role_label' => 'Developer', 'status' => 'pending_password', 'joined_at' => null, 'added_at' => '2026-11-01T00:00:00Z',
            ]],
            'you_can_manage' => true,
            'assignable_roles' => ['admin', 'developer', 'operator', 'read-only'],
            'role_summaries' => ['admin' => 'Manages the team.'],
            'seats' => ['used' => 2, 'limit' => 5],
        ];
        $team = $this->client([self::ok($data)])->team->list(strtoupper(self::TENANT));

        $this->assertRequest('GET', self::BASE . '/tenants/' . self::TENANT . '/members');
        self::assertSame('Acme', $team->tenant->name);
        self::assertSame('ada@example.com', $team->owner->email);
        self::assertCount(1, $team->members);
        self::assertSame('pending_password', $team->members[0]->status);
        self::assertSame('Developer', $team->members[0]->roleLabel);
        self::assertNull($team->members[0]->joinedAt);
        self::assertTrue($team->youCanManage);
        self::assertSame(['admin', 'developer', 'operator', 'read-only'], $team->assignableRoles);
        self::assertSame(['admin' => 'Manages the team.'], $team->roleSummaries);
        self::assertSame(5, $team->seats?->limit);
    }

    public function testTeamListWhenYouCannotManage(): void
    {
        $team = $this->client([self::ok(['members' => [], 'you_can_manage' => false, 'assignable_roles' => null, 'seats' => null])])
            ->team->list(self::TENANT);

        self::assertNull($team->assignableRoles);
        self::assertNull($team->seats);
    }

    public function testTeamAdd(): void
    {
        $member = ['id' => self::MEMBER, 'name' => 'Sam Lee', 'email' => 'sam@example.com', 'role' => 'developer', 'status' => 'pending_password'];
        $result = $this->client([self::ok(['member' => $member, 'message' => 'Sam Lee was added.'], 201)])
            ->team->add(self::TENANT, name: 'Sam Lee', email: 'sam@example.com', role: 'developer');

        $this->assertRequest('POST', self::BASE . '/tenants/' . self::TENANT . '/members', [
            'name' => 'Sam Lee', 'email' => 'sam@example.com', 'role' => 'developer',
        ]);
        self::assertSame(self::MEMBER, $result->member->id);
        self::assertSame('Sam Lee was added.', $result->message);
    }

    public function testTeamAddAcceptsTheEnum(): void
    {
        $this->client([self::ok(['member' => [], 'message' => 'ok'], 201)])
            ->team->add(self::TENANT, name: 'Sam Lee', email: 'sam@example.com', role: AssignableRole::ReadOnly);

        self::assertSame('read-only', self::body($this->http->lastRequest())['role'] ?? null);
    }

    public function testTeamChangeRoleSendsConfirm(): void
    {
        $result = $this->client([self::ok(['member' => ['id' => self::MEMBER, 'role' => 'admin'], 'message' => 'Changed.'])])
            ->team->changeRole(self::TENANT, self::MEMBER, role: AssignableRole::Admin);

        $this->assertRequest('PATCH', self::BASE . '/tenants/' . self::TENANT . '/members/' . self::MEMBER, [
            'role' => 'admin', 'confirm' => true,
        ]);
        self::assertSame('admin', $result->member->role);
    }

    public function testTeamRemoveSendsConfirm(): void
    {
        $result = $this->client([self::ok(['removed' => true, 'email' => 'sam@example.com', 'tenant_id' => self::TENANT])])
            ->team->remove(self::TENANT, self::MEMBER);

        $this->assertRequest('DELETE', self::BASE . '/tenants/' . self::TENANT . '/members/' . self::MEMBER, ['confirm' => true]);
        self::assertTrue($result->removed);
        self::assertSame('sam@example.com', $result->email);
        self::assertSame(self::TENANT, $result->tenantId);
    }

    public function testTeamResendInvite(): void
    {
        $result = $this->client([self::ok(['sent' => true, 'email' => 'sam@example.com'])])
            ->team->resendInvite(self::TENANT, self::MEMBER);

        $this->assertRequest('POST', self::BASE . '/tenants/' . self::TENANT . '/members/' . self::MEMBER . '/resend');
        self::assertTrue($result->sent);
        self::assertSame('sam@example.com', $result->email);
    }

    public function testAgentsListWithoutFilters(): void
    {
        $agents = $this->client([self::ok(['agents' => []])])->agents->list();

        $this->assertRequest('GET', self::BASE . '/agents');
        self::assertSame([], $agents);
    }

    public function testAgentsListWithFilters(): void
    {
        $data = ['agents' => [[
            'id' => self::AGENT, 'tenant_id' => self::TENANT, 'tenant_name' => 'Acme', 'name' => 'db-01', 'hostname' => 'db-01.local',
            'status' => 'active', 'connection' => 'online', 'update_channel' => 'stable', 'desired_role' => null,
            'last_seen_at' => '2026-11-20T10:00:00Z', 'registered_at' => '2026-01-01T00:00:00Z', 'revoked_at' => null,
            'latest_command' => ['type' => 'sync', 'status' => 'succeeded', 'created_at' => '2026-11-20T09:00:00Z'],
        ]]];
        $agents = $this->client([self::ok($data)])->agents->list(tenantId: self::TENANT, status: AgentStatus::Active);

        $this->assertRequest('GET', self::BASE . '/agents?tenant_id=' . self::TENANT . '&status=active');
        self::assertSame('db-01', $agents[0]->name);
        self::assertSame(self::TENANT, $agents[0]->tenantId);
        self::assertSame('stable', $agents[0]->updateChannel);
        self::assertSame('sync', $agents[0]->latestCommand?->type);
    }

    public function testAgentsListWithStatusString(): void
    {
        $this->client([self::ok(['agents' => []])])->agents->list(status: 'pending');

        $this->assertRequest('GET', self::BASE . '/agents?status=pending');
    }

    public function testInstancesList(): void
    {
        $data = ['instances' => [[
            'id' => self::AGENT, 'tenant_id' => self::TENANT, 'tenant_name' => 'Acme', 'hostname' => null, 'public_url' => 'https://acme.example.com',
            'hosting_mode' => 'hosted', 'status' => 'running', 'revision' => 4, 'applied_revision' => 3,
            'last_seen_at' => null, 'created_at' => '2026-01-01T00:00:00Z',
        ]]];
        $instances = $this->client([self::ok($data)])->instances->list(tenantId: self::TENANT);

        $this->assertRequest('GET', self::BASE . '/instances?tenant_id=' . self::TENANT);
        self::assertSame('hosted', $instances[0]->hostingMode);
        self::assertSame(4, $instances[0]->revision);
        self::assertSame(3, $instances[0]->appliedRevision);
        self::assertSame('https://acme.example.com', $instances[0]->publicUrl);
    }

    public function testInstancesListWithoutFilter(): void
    {
        $this->client([self::ok(['instances' => []])])->instances->list();

        $this->assertRequest('GET', self::BASE . '/instances');
    }

    public function testSupportList(): void
    {
        $data = ['tickets' => [[
            'id' => self::TICKET, 'subject' => 'Agent offline', 'status' => 'open', 'status_label' => 'Open', 'priority' => 'high',
            'tenant' => ['id' => self::TENANT, 'name' => 'Acme'], 'created_at' => '2026-11-20T00:00:00Z',
            'updated_at' => '2026-11-20T01:00:00Z', 'portal_url' => 'https://portal.sql-cockpit.com/support/1',
        ]]];
        $tickets = $this->client([self::ok($data)])->support->list(status: TicketStatus::WaitingOnCustomer);

        $this->assertRequest('GET', self::BASE . '/support-tickets?status=waiting_on_customer');
        self::assertSame('Agent offline', $tickets[0]->subject);
        self::assertSame('Acme', $tickets[0]->tenant?->name);
        self::assertSame('high', $tickets[0]->priority);
    }

    public function testSupportListWithoutFilter(): void
    {
        $this->client([self::ok(['tickets' => []])])->support->list();

        $this->assertRequest('GET', self::BASE . '/support-tickets');
    }

    public function testSupportGet(): void
    {
        $data = ['ticket' => [
            'id' => self::TICKET, 'subject' => 'Agent offline', 'status' => 'resolved', 'status_label' => 'Resolved', 'priority' => 'normal',
            'tenant' => null, 'created_at' => '2026-11-20T00:00:00Z', 'updated_at' => '2026-11-20T01:00:00Z', 'portal_url' => 'https://x',
            'message' => 'The Agent stopped reporting.', 'agent' => ['id' => self::AGENT, 'name' => 'db-01', 'hostname' => null],
            'team_response' => 'Restarted.', 'responded_at' => '2026-11-20T00:30:00Z', 'resolved_at' => '2026-11-20T01:00:00Z', 'closed_at' => null,
            'replies' => [['from' => 'sql_cockpit_team', 'body' => 'Fixed.', 'created_at' => '2026-11-20T00:40:00Z']],
        ]];
        $ticket = $this->client([self::ok($data)])->support->get(self::TICKET);

        $this->assertRequest('GET', self::BASE . '/support-tickets/' . self::TICKET);
        self::assertInstanceOf(Ticket::class, $ticket);
        self::assertSame('The Agent stopped reporting.', $ticket->message);
        self::assertSame('db-01', $ticket->agent?->name);
        self::assertSame('Restarted.', $ticket->teamResponse);
        self::assertNull($ticket->tenant);
        self::assertSame('sql_cockpit_team', $ticket->replies[0]->from);
        self::assertSame($data['ticket'], $ticket->toArray());
    }

    public function testSupportCreate(): void
    {
        $ticket = $this->client([self::ok(['ticket' => ['id' => self::TICKET, 'subject' => 'Help please', 'priority' => 'high', 'message' => 'Ticket sent.']], 201)])
            ->support->create(subject: 'Help please', message: 'The Agent stopped reporting.', tenantId: self::TENANT, agentId: self::AGENT);

        $this->assertRequest('POST', self::BASE . '/support-tickets', [
            'subject' => 'Help please', 'message' => 'The Agent stopped reporting.', 'tenant_id' => self::TENANT, 'agent_id' => self::AGENT,
        ]);
        self::assertSame(self::TICKET, $ticket->id);
        self::assertSame('Ticket sent.', $ticket->message);
        self::assertSame([], $ticket->replies);
    }

    public function testSupportCreateWithoutOptionalIds(): void
    {
        $this->client([self::ok(['ticket' => ['id' => self::TICKET]], 201)])
            ->support->create(subject: 'Help please', message: 'Something is wrong here.');

        $this->assertRequest('POST', self::BASE . '/support-tickets', ['subject' => 'Help please', 'message' => 'Something is wrong here.']);
    }
}
