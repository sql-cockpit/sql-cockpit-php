<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Data;
use SqlCockpit\Model\ApiKey;

/**
 * Your portal API keys. Creating and revoking keys need keys:write, which an API key never
 * holds, so this client only lists them. Manage keys in the portal under Settings > Developer access.
 */
final class ApiKeysResource
{
    /**
     * @internal Use $client->apiKeys.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /api-keys: your API keys, newest first, with their status and last four characters.
     *
     * @return list<ApiKey>
     */
    public function list(): array
    {
        $data = $this->requester->request('GET', '/api-keys');

        return array_map(static fn (array $item): ApiKey => ApiKey::fromArray($item), Data::objects($data, 'keys'));
    }
}
