# Changelog

## 0.1.0 - 2026-11-21

First release.

- `SqlCockpit\Client` for the portal API v1: account, usage, API keys (list), tenants, team members, Agents, instances and support tickets.
- Readonly value objects for every response, with camelCase properties and `toArray()` for the original data.
- Exceptions by HTTP status: `AuthenticationException`, `PermissionDeniedException`, `NotFoundException`, `ValidationException`, `RateLimitException`, `ServerException`, plus `ConnectionException` and `TimeoutException`, all implementing `SqlCockpitException`.
- GET requests retry on 429 and 5xx (up to 2 times, honouring Retry-After up to 30 seconds). Writes are never retried.
- Any PSR-18 client, discovered with php-http/discovery, or a built-in cURL transport.
- https only (http allowed for localhost), redirects refused, responses capped at 2 MiB, the API key kept out of dumps, exceptions and serialisation.
