<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Enum\AgentStatus;
use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Data;
use SqlCockpit\Internal\Ids;
use SqlCockpit\Model\Agent;

/**
 * The SQL Cockpit Agents paired with your tenants.
 */
final class AgentsResource
{
    /**
     * @internal Use $client->agents.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /agents: the Agents of the tenants you can reach, with their connection and the latest
     * command each one ran.
     *
     * @param string|null             $tenantId Only the Agents of this tenant (a UUID).
     * @param AgentStatus|string|null $status   Only Agents in this status.
     *
     * @return list<Agent>
     */
    public function list(?string $tenantId = null, AgentStatus|string|null $status = null): array
    {
        $query = [
            'tenant_id' => Ids::optionalUuid($tenantId, 'tenantId'),
            'status' => $status instanceof AgentStatus ? $status->value : $status,
        ];
        $data = $this->requester->request('GET', '/agents', $query);

        return array_map(static fn (array $item): Agent => Agent::fromArray($item), Data::objects($data, 'agents'));
    }
}
