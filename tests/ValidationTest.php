<?php

declare(strict_types=1);

namespace SqlCockpit\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use SqlCockpit\Client;
use SqlCockpit\Exception\InvalidConfigurationException;
use SqlCockpit\Exception\SqlCockpitException;
use SqlCockpit\Exception\ValidationException;
use SqlCockpit\Tests\Support\ClientTestCase;
use SqlCockpit\Tests\Support\FakeHttpClient;

final class ValidationTest extends ClientTestCase
{
    /**
     * @return iterable<string, array{\Closure(Client): mixed, string}>
     */
    public static function badIds(): iterable
    {
        $bad = '../../admin';
        $t = self::TENANT;
        $m = self::MEMBER;

        yield 'team.list tenantId' => [static fn (Client $c) => $c->team->list($bad), 'tenantId'];
        yield 'team.add tenantId' => [static fn (Client $c) => $c->team->add($bad, name: 'Sam Lee', email: 's@example.com', role: 'developer'), 'tenantId'];
        yield 'team.changeRole tenantId' => [static fn (Client $c) => $c->team->changeRole($bad, $m, role: 'admin'), 'tenantId'];
        yield 'team.changeRole membershipId' => [static fn (Client $c) => $c->team->changeRole($t, $bad, role: 'admin'), 'membershipId'];
        yield 'team.remove tenantId' => [static fn (Client $c) => $c->team->remove($bad, $m), 'tenantId'];
        yield 'team.remove membershipId' => [static fn (Client $c) => $c->team->remove($t, 'not-a-uuid'), 'membershipId'];
        yield 'team.resendInvite membershipId' => [static fn (Client $c) => $c->team->resendInvite($t, $m . 'x'), 'membershipId'];
        yield 'agents.list tenantId' => [static fn (Client $c) => $c->agents->list(tenantId: $bad), 'tenantId'];
        yield 'instances.list tenantId' => [static fn (Client $c) => $c->instances->list(tenantId: 'acme'), 'tenantId'];
        yield 'support.get ticketId' => [static fn (Client $c) => $c->support->get('42'), 'ticketId'];
        yield 'support.create tenantId' => [static fn (Client $c) => $c->support->create(subject: 'Help please', message: 'Something is wrong.', tenantId: $bad), 'tenantId'];
        yield 'support.create agentId' => [static fn (Client $c) => $c->support->create(subject: 'Help please', message: 'Something is wrong.', tenantId: $t, agentId: $bad), 'agentId'];
    }

    /**
     * @param \Closure(Client): mixed $call
     */
    #[DataProvider('badIds')]
    public function testBadIdIsRefusedBeforeAnythingIsSent(\Closure $call, string $field): void
    {
        $client = $this->client([self::ok([])]);

        try {
            $call($client);
            self::fail('No exception was thrown.');
        } catch (ValidationException $error) {
            self::assertSame('invalid_argument', $error->errorCode);
            self::assertSame(0, $error->status);
            self::assertSame([$field => ['Must be a UUID.']], $error->fields);
        }
        self::assertSame([], $this->http->requests);
    }

    public function testEmptyFilterIdsAreLeftOut(): void
    {
        $client = $this->client([self::ok(['agents' => []])]);
        $client->agents->list(tenantId: '', status: '');

        $this->assertRequest('GET', self::BASE . '/agents');
    }

    public function testInvalidUtf8IsRefusedBeforeSending(): void
    {
        $client = $this->client([self::ok([])]);

        try {
            $client->support->create(subject: "Bad \xB1 text", message: 'Something is wrong here.');
            self::fail('No exception was thrown.');
        } catch (ValidationException $error) {
            self::assertSame('invalid_argument', $error->errorCode);
        }
        self::assertSame([], $this->http->requests);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badPortalUrls(): iterable
    {
        yield 'http on a public host' => ['http://portal.sql-cockpit.com'];
        yield 'credentials' => ['https://user:pass@portal.sql-cockpit.com'];
        yield 'user only' => ['https://user@portal.sql-cockpit.com'];
        yield 'query' => ['https://portal.sql-cockpit.com/?a=1'];
        yield 'empty query' => ['https://portal.sql-cockpit.com/?'];
        yield 'fragment' => ['https://portal.sql-cockpit.com/#top'];
        yield 'other scheme' => ['ftp://portal.sql-cockpit.com'];
        yield 'no scheme' => ['portal.sql-cockpit.com'];
        yield 'space' => ['https://portal .sql-cockpit.com'];
        yield 'http on a localhost lookalike' => ['http://localhost.evil.com'];
    }

    #[DataProvider('badPortalUrls')]
    public function testBadPortalUrlIsRefused(string $url): void
    {
        $this->expectException(InvalidConfigurationException::class);
        Client::normalizePortalUrl($url);
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function goodPortalUrls(): iterable
    {
        yield 'default' => [null, 'https://portal.sql-cockpit.com'];
        yield 'trailing slash' => ['https://portal.sql-cockpit.com/', 'https://portal.sql-cockpit.com'];
        yield 'api base url' => ['https://portal.sql-cockpit.com/api/portal/v1/', 'https://portal.sql-cockpit.com'];
        yield 'upper case' => ['HTTPS://Portal.SQL-Cockpit.com', 'https://portal.sql-cockpit.com'];
        yield 'localhost' => ['http://localhost:8000', 'http://localhost:8000'];
        yield '127.0.0.1' => ['http://127.0.0.1:8000/', 'http://127.0.0.1:8000'];
        yield '[::1]' => ['http://[::1]:8000', 'http://[::1]:8000'];
        yield 'sub path' => ['https://example.com/portal', 'https://example.com/portal'];
    }

    #[DataProvider('goodPortalUrls')]
    public function testGoodPortalUrlIsNormalised(?string $url, string $expected): void
    {
        self::assertSame($expected, Client::normalizePortalUrl($url));
    }

    public function testLocalPortalUrlIsUsedForRequests(): void
    {
        $http = new FakeHttpClient([self::ok(['tenants' => []])]);
        $factory = new Psr17Factory();
        $client = new Client(apiKey: self::API_KEY, portalUrl: 'http://127.0.0.1:8000/api/portal/v1', httpClient: $http, requestFactory: $factory, streamFactory: $factory);
        $client->tenants->list();

        self::assertSame('http://127.0.0.1:8000/api/portal/v1/tenants', (string) $http->lastRequest()->getUri());
    }

    public function testMissingApiKeyIsRefused(): void
    {
        $previous = getenv('SQL_COCKPIT_API_KEY');
        putenv('SQL_COCKPIT_API_KEY');
        unset($_ENV['SQL_COCKPIT_API_KEY'], $_SERVER['SQL_COCKPIT_API_KEY']);
        try {
            new Client();
            self::fail('No exception was thrown.');
        } catch (InvalidConfigurationException $error) {
            self::assertInstanceOf(SqlCockpitException::class, $error);
            self::assertStringContainsString('SQL_COCKPIT_API_KEY', $error->getMessage());
        } finally {
            if (\is_string($previous)) {
                putenv('SQL_COCKPIT_API_KEY=' . $previous);
            }
        }
    }

    public function testMalformedApiKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Client(apiKey: 'short');
    }

    public function testDefaultsComeFromTheEnvironment(): void
    {
        putenv('SQL_COCKPIT_API_KEY=' . self::API_KEY);
        putenv('SQL_COCKPIT_PORTAL_URL=http://localhost:9000');
        try {
            $http = new FakeHttpClient([self::ok(['name' => 'Ada'])]);
            $factory = new Psr17Factory();
            $client = new Client(httpClient: $http, requestFactory: $factory, streamFactory: $factory);

            self::assertSame('http://localhost:9000', $client->portalUrl);
            self::assertSame(20.0, $client->timeout);
            self::assertSame(2, $client->maxRetries);
            self::assertSame('Ada', $client->account->get()->name);
            self::assertSame('Bearer ' . self::API_KEY, $http->lastRequest()->getHeaderLine('Authorization'));
        } finally {
            putenv('SQL_COCKPIT_API_KEY');
            putenv('SQL_COCKPIT_PORTAL_URL');
        }
    }

    /**
     * @return iterable<string, array{float, int}>
     */
    public static function badSettings(): iterable
    {
        yield 'zero timeout' => [0.0, 2];
        yield 'negative timeout' => [-1.0, 2];
        yield 'huge timeout' => [601.0, 2];
        yield 'infinite timeout' => [INF, 2];
        yield 'negative retries' => [20.0, -1];
        yield 'too many retries' => [20.0, 3];
    }

    #[DataProvider('badSettings')]
    public function testBadSettingsAreRefused(float $timeout, int $maxRetries): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new Client(apiKey: self::API_KEY, timeout: $timeout, maxRetries: $maxRetries, httpClient: new FakeHttpClient());
    }

    public function testBuiltInTransportIsUsedWithoutAPsr18Client(): void
    {
        if (!\function_exists('curl_init')) {
            self::markTestSkipped('ext-curl is not installed.');
        }
        $client = new Client(apiKey: self::API_KEY);
        $requester = (new \ReflectionProperty(Client::class, 'requester'))->getValue($client);
        self::assertIsObject($requester);
        $transport = (new \ReflectionProperty($requester, 'transport'))->getValue($requester);

        self::assertInstanceOf(\SqlCockpit\Http\CurlTransport::class, $transport);
    }
}
