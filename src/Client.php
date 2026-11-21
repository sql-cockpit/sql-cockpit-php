<?php

declare(strict_types=1);

namespace SqlCockpit;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SqlCockpit\Exception\InvalidConfigurationException;
use SqlCockpit\Exception\SerializationException;
use SqlCockpit\Http\Requester;
use SqlCockpit\Http\TransportFactory;
use SqlCockpit\Resource\AccountResource;
use SqlCockpit\Resource\AgentsResource;
use SqlCockpit\Resource\ApiKeysResource;
use SqlCockpit\Resource\InstancesResource;
use SqlCockpit\Resource\SupportResource;
use SqlCockpit\Resource\TeamResource;
use SqlCockpit\Resource\TenantsResource;

/**
 * The SQL Cockpit portal API client.
 *
 * The API key is never stored on a visible property: var_dump, print_r, var_export and
 * json_encode show only the portal URL and settings, and the client cannot be serialised.
 */
final class Client implements \JsonSerializable
{
    public const VERSION = '0.1.0';
    public const DEFAULT_PORTAL_URL = 'https://portal.sql-cockpit.com';
    public const API_PATH = '/api/portal/v1';
    public const DEFAULT_TIMEOUT = 20.0;
    public const DEFAULT_MAX_RETRIES = 2;

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    /** The portal's address, without the API path, for example https://portal.sql-cockpit.com. */
    public readonly string $portalUrl;

    /** How long one attempt may take, in seconds. */
    public readonly float $timeout;

    /** How many times a failed GET (429 or 5xx) is retried. */
    public readonly int $maxRetries;

    public readonly AccountResource $account;
    public readonly ApiKeysResource $apiKeys;
    public readonly TenantsResource $tenants;
    public readonly TeamResource $team;
    public readonly AgentsResource $agents;
    public readonly InstancesResource $instances;
    public readonly SupportResource $support;

    private readonly Requester $requester;

    /**
     * @param string|null                  $apiKey         A portal API key. Defaults to SQL_COCKPIT_API_KEY.
     * @param string|null                  $portalUrl      Defaults to SQL_COCKPIT_PORTAL_URL, then https://portal.sql-cockpit.com.
     * @param float                        $timeout        Seconds one attempt may take, more than 0 and at most 600.
     * @param int                          $maxRetries     0, 1 or 2. GET requests only; writes are never retried.
     * @param ClientInterface|null         $httpClient     A PSR-18 client to use. Configure its timeout and turn redirects off yourself.
     * @param RequestFactoryInterface|null $requestFactory A PSR-17 request factory. Discovered when not given.
     * @param StreamFactoryInterface|null  $streamFactory  A PSR-17 stream factory. Discovered when not given.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        #[\SensitiveParameter] ?string $apiKey = null,
        ?string $portalUrl = null,
        float $timeout = self::DEFAULT_TIMEOUT,
        int $maxRetries = self::DEFAULT_MAX_RETRIES,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        // Overwrite the argument at once: a stack trace shows the current value of each argument,
        // so an exception thrown below never carries the key, even on PHP 8.1.
        $key = $apiKey ?? self::env('SQL_COCKPIT_API_KEY');
        $apiKey = null;
        $key = self::normalizeApiKey($key);

        $this->portalUrl = self::normalizePortalUrl($portalUrl ?? self::env('SQL_COCKPIT_PORTAL_URL'));
        if (!is_finite($timeout) || $timeout <= 0 || $timeout > 600) {
            throw new InvalidConfigurationException('timeout must be a number of seconds more than 0 and at most 600.');
        }
        if ($maxRetries < 0 || $maxRetries > 2) {
            throw new InvalidConfigurationException('maxRetries must be 0, 1 or 2.');
        }
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;

        $transport = TransportFactory::create($httpClient, $requestFactory, $streamFactory, $timeout);
        $this->requester = new Requester($key, $this->portalUrl . self::API_PATH, $transport, $timeout, $maxRetries);
        $key = '';

        $this->account = new AccountResource($this->requester);
        $this->apiKeys = new ApiKeysResource($this->requester);
        $this->tenants = new TenantsResource($this->requester);
        $this->team = new TeamResource($this->requester);
        $this->agents = new AgentsResource($this->requester);
        $this->instances = new InstancesResource($this->requester);
        $this->support = new SupportResource($this->requester);
    }

    /**
     * What var_dump and print_r show. The API key is never included.
     *
     * @return array{portalUrl: string, timeout: float, maxRetries: int}
     */
    public function __debugInfo(): array
    {
        return ['portalUrl' => $this->portalUrl, 'timeout' => $this->timeout, 'maxRetries' => $this->maxRetries];
    }

    /**
     * What json_encode shows. The API key is never included.
     *
     * @return array{portalUrl: string, timeout: float, maxRetries: int}
     */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new SerializationException('The SQL Cockpit client holds an API key and cannot be serialised.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new SerializationException('The SQL Cockpit client cannot be unserialised.');
    }

    /**
     * https only, except http on localhost, 127.0.0.1 and [::1]. No credentials, query or fragment.
     * The API base URL (ending in /api/portal/v1) is accepted too.
     *
     * @throws InvalidConfigurationException
     */
    public static function normalizePortalUrl(?string $value): string
    {
        $value = trim($value ?? '');
        if ($value === '') {
            $value = self::DEFAULT_PORTAL_URL;
        }
        if (preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw new InvalidConfigurationException('portalUrl is not a valid URL.');
        }
        $parts = parse_url($value);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            throw new InvalidConfigurationException('portalUrl is not a valid URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || str_contains($value, '?') || str_contains($value, '#')) {
            throw new InvalidConfigurationException('portalUrl must not contain credentials, a query string or a fragment.');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $loopbackHttp = $scheme === 'http' && \in_array($host, self::LOOPBACK_HOSTS, true);
        if ($scheme !== 'https' && !$loopbackHttp) {
            throw new InvalidConfigurationException('portalUrl must use https:// (http:// is only allowed for localhost).');
        }

        $path = rtrim($parts['path'] ?? '', '/');
        if (preg_match('#/api/portal/v1$#i', $path) === 1) {
            $path = substr($path, 0, -\strlen(self::API_PATH));
        }
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port . rtrim($path, '/');
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function normalizeApiKey(#[\SensitiveParameter] ?string $value): string
    {
        $key = trim($value ?? '');
        $value = null;
        if ($key === '') {
            throw new InvalidConfigurationException(
                'An API key is required. Pass apiKey or set SQL_COCKPIT_API_KEY. '
                . 'Create a key in the portal under Settings > Developer access.'
            );
        }
        if (\strlen($key) < 20 || \strlen($key) > 200 || preg_match('/[\s\x00-\x1f\x7f]/', $key) === 1) {
            $key = '';
            throw new InvalidConfigurationException('The API key does not look like a SQL Cockpit portal API key.');
        }

        return $key;
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);
        if (!\is_string($value) || $value === '') {
            $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
        }

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
