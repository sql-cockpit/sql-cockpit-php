<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Data;
use SqlCockpit\Model\Tenant;

/**
 * The tenants you own or belong to.
 */
final class TenantsResource
{
    /**
     * @internal Use $client->tenants.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /tenants: every tenant you own or are a member of, with your role and what it lets you do.
     *
     * @return list<Tenant>
     */
    public function list(): array
    {
        $data = $this->requester->request('GET', '/tenants');

        return array_map(static fn (array $item): Tenant => Tenant::fromArray($item), Data::objects($data, 'tenants'));
    }
}
