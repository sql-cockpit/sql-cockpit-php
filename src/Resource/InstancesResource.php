<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Data;
use SqlCockpit\Internal\Ids;
use SqlCockpit\Model\Instance;

/**
 * Hosted and self-hosted SQL Cockpit Server instances.
 */
final class InstancesResource
{
    /**
     * @internal Use $client->instances.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /instances: the SQL Cockpit Server instances of the tenants you can reach.
     *
     * @param string|null $tenantId Only the instances of this tenant (a UUID).
     *
     * @return list<Instance>
     */
    public function list(?string $tenantId = null): array
    {
        $data = $this->requester->request('GET', '/instances', ['tenant_id' => Ids::optionalUuid($tenantId, 'tenantId')]);

        return array_map(static fn (array $item): Instance => Instance::fromArray($item), Data::objects($data, 'instances'));
    }
}
