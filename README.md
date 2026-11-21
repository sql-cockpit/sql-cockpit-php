# SQL Cockpit PHP client

The official PHP client for the SQL Cockpit portal API. Use it to read your account, API keys, tenants, team, Agents, instances and support tickets, and to manage team members and open tickets from your own code.

It needs PHP 8.1 or later. A portal API key is a secret, so use the client on a server, not in anything you ship to a browser.

## Install

The package is not on Packagist yet. Add this repository to your project and require it:

```sh
composer config repositories.sql-cockpit vcs https://github.com/sql-cockpit/sql-cockpit-php
composer require sql-cockpit/sdk:^0.1
```

Composer asks whether to trust the `php-http/discovery` plugin. Either answer works: the client finds an HTTP client without it.

### Dependencies

The client keeps its runtime dependencies small:

| Package | Why |
| --- | --- |
| `psr/http-client`, `psr/http-factory`, `psr/http-message` | The standard interfaces (PSR-18, PSR-17, PSR-7), so you can pass any HTTP client you already use. Interfaces only, no code. |
| `php-http/discovery` | Finds a PSR-18 client and PSR-17 factories that are already installed. |

You do not need to install an HTTP client. When Symfony HttpClient or Guzzle 7 is installed, the client uses it, set up with your timeout and with redirects turned off. Otherwise it uses its own small transport built on the curl extension, which nearly every PHP install has.

## Your first program

Create an API key in the portal under **Settings > Developer access**, then put it in the environment:

```sh
export SQL_COCKPIT_API_KEY=your-key
```

```php
<?php

require __DIR__ . '/vendor/autoload.php';

$client = new SqlCockpit\Client(); // reads SQL_COCKPIT_API_KEY

$account = $client->account->get();
echo 'Name:  ', $account->name, PHP_EOL;
echo 'Email: ', $account->email, PHP_EOL;
echo 'Plan:  ', $account->plan->name ?? 'no plan', PHP_EOL;
```

The same program is in [examples/first-call.php](examples/first-call.php).

## Methods

Each method sends one request and returns the response's `data` as readonly objects. Properties are camelCase (`$ticket->createdAt`, `$agent->tenantId`); `toArray()` gives back the data exactly as the API sent it, with its snake_case keys. List methods return arrays of objects. Dates are ISO 8601 strings.

| Method | HTTP operation | Returns |
| --- | --- | --- |
| `$client->account->get()` | `GET /account` | `Account` |
| `$client->account->usage()` | `GET /usage` | `Usage` |
| `$client->apiKeys->list()` | `GET /api-keys` | `ApiKey[]` |
| `$client->tenants->list()` | `GET /tenants` | `Tenant[]` |
| `$client->team->list($tenantId)` | `GET /tenants/{tenantId}/members` | `Team` (owner, members, assignable roles, seats) |
| `$client->team->add($tenantId, name: , email: , role: )` | `POST /tenants/{tenantId}/members` | `MemberResult` |
| `$client->team->changeRole($tenantId, $membershipId, role: )` | `PATCH /tenants/{tenantId}/members/{membershipId}` | `MemberResult` |
| `$client->team->remove($tenantId, $membershipId)` | `DELETE /tenants/{tenantId}/members/{membershipId}` | `RemovedResult` |
| `$client->team->resendInvite($tenantId, $membershipId)` | `POST /tenants/{tenantId}/members/{membershipId}/resend` | `SentResult` |
| `$client->agents->list(tenantId: null, status: null)` | `GET /agents` | `Agent[]` |
| `$client->instances->list(tenantId: null)` | `GET /instances` | `Instance[]` |
| `$client->support->list(status: null)` | `GET /support-tickets` | `TicketSummary[]` |
| `$client->support->get($ticketId)` | `GET /support-tickets/{ticketId}` | `Ticket` |
| `$client->support->create(subject: , message: , tenantId: null, agentId: null)` | `POST /support-tickets` | `Ticket` |

The objects live in `SqlCockpit\Model`. Roles and statuses can be passed as strings or as the enums in `SqlCockpit\Enum` (`AssignableRole`, `AgentStatus`, `TicketStatus`):

```php
use SqlCockpit\Enum\AssignableRole;

$result = $client->team->add($tenantId, name: 'Sam Lee', email: 'sam@example.com', role: AssignableRole::Developer);
echo $result->member->status; // pending_password until Sam sets a password
```

`team->changeRole()` and `team->remove()` send `confirm: true` for you. The API asks for it so a change is never made by accident, so only call them when you mean it.

Ids in a path (`$tenantId`, `$membershipId`, `$ticketId`) and the `tenantId` and `agentId` filters must be UUIDs. Anything else is refused with a `ValidationException` before a request is sent.

### API keys cannot create API keys

`$client->apiKeys->list()` shows your keys, their status and last four characters, never their values. Creating and revoking keys needs the `keys:write` scope, which an API key never holds (only a person signing in through OAuth can grant it), so this client does not offer those calls. Create and revoke keys in the portal under **Settings > Developer access**.

Plans, billing and deleting anything other than a team membership are not part of the API either. Do those in the portal.

## Errors

Every exception the client throws implements `SqlCockpit\Exception\SqlCockpitException`. A failed call throws an `ApiException` or one of its subclasses, with:

- `$errorCode` (or `getErrorCode()`): the API's error code, such as `not_found` or `validation_failed`, or one of the client's own: `invalid_argument`, `unexpected_redirect`, `invalid_response`, `response_too_large`
- `$status` (or `getStatus()`, `getCode()`): the HTTP status, or 0 when no request was sent
- `getMessage()`: what went wrong, in plain words
- `$fields` (or `getFields()`): for validation errors, the messages for each field
- `$retryAfter` (or `getRetryAfter()`): for 429, the seconds to wait, when the portal sent Retry-After

| Exception | When |
| --- | --- |
| `AuthenticationException` | 401. The key is missing, wrong, revoked or expired. |
| `PermissionDeniedException` | 403. The key lacks the scope, your plan has no room, or your role cannot do this. |
| `NotFoundException` | 404. Not found, or not something your account can reach. |
| `ValidationException` | 400 or 422, or a bad id caught before sending. See `$fields`. |
| `RateLimitException` | 429. See `$retryAfter`. |
| `ServerException` | 5xx. |
| `ApiException` | Any other failure: a redirect, a response that is not JSON, or one over 2 MiB. |
| `ConnectionException` | The portal could not be reached. |
| `TimeoutException` | No answer within the timeout. It extends `ConnectionException`. |
| `InvalidConfigurationException` | No API key, a portal URL that is not https, or a setting out of range. |

```php
use SqlCockpit\Exception\NotFoundException;
use SqlCockpit\Exception\ValidationException;

try {
    $client->team->add($tenantId, name: 'Sam Lee', email: 'sam@example.com', role: 'developer');
} catch (ValidationException $e) {
    print_r($e->fields);
} catch (NotFoundException $e) {
    echo 'No such tenant.', PHP_EOL;
}
```

GET requests are retried on 429 and 5xx, up to `maxRetries` times (2 by default). The client waits for Retry-After when the portal sends it, but never more than 30 seconds. Writes (POST, PATCH, DELETE) are never retried, so a ticket or invitation is never sent twice.

## Configuration

```php
$client = new SqlCockpit\Client(
    apiKey: 'your-key',
    portalUrl: 'https://portal.sql-cockpit.com',
    timeout: 20.0,
    maxRetries: 2,
    httpClient: null,
);
```

| Argument | Default | Notes |
| --- | --- | --- |
| `apiKey` | `SQL_COCKPIT_API_KEY` | Required. |
| `portalUrl` | `SQL_COCKPIT_PORTAL_URL`, then `https://portal.sql-cockpit.com` | Must use https. http is allowed for `localhost`, `127.0.0.1` and `[::1]`. No credentials, query or fragment. |
| `timeout` | `20.0` | Seconds per attempt. |
| `maxRetries` | `2` | 0 to 2. GET requests only. |
| `httpClient` | discovered | Any PSR-18 client. Set its timeout and turn redirects off yourself. |
| `requestFactory`, `streamFactory` | discovered | PSR-17 factories for your `httpClient`, if discovery cannot find them. |

## Safety

- The API key is never stored on a visible property. `var_dump`, `print_r`, `var_export` and `json_encode` of the client show only the URL and settings, and the client cannot be serialised.
- The key is removed from any error message that echoes it, never appears in an exception or its stack trace, and the HTTP client's own exceptions (which carry the request) are never chained.
- Redirects are refused, not followed, so the key is never sent to another address.
- Responses over 2 MiB are refused.
- Each request sends `User-Agent: sqlcockpit-php/<version>`.

## Development

```sh
composer install
composer test
composer analyse
```

`composer analyse` runs PHPStan at level 8.

## License

MIT
