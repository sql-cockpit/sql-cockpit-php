<?php

declare(strict_types=1);

namespace SqlCockpit\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use SqlCockpit\Client;
use SqlCockpit\Http\Requester;

abstract class ClientTestCase extends TestCase
{
    public const API_KEY = 'sco_live_TESTKEY0123456789abcdefghijklmnop';
    public const BASE = 'https://portal.sql-cockpit.com/api/portal/v1';
    public const TENANT = '11111111-2222-4333-8444-555555555555';
    public const MEMBER = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    public const TICKET = '99999999-8888-4777-8666-555555555555';
    public const AGENT = '12345678-1234-4123-8123-123456789abc';

    protected FakeHttpClient $http;

    /** @var list<float> */
    protected array $sleeps = [];

    /**
     * @param list<\Psr\Http\Message\ResponseInterface|\Throwable> $responses
     */
    protected function client(array $responses = [], int $maxRetries = 2): Client
    {
        $this->http = new FakeHttpClient($responses);
        $factory = new Psr17Factory();
        $client = new Client(
            apiKey: self::API_KEY,
            portalUrl: 'https://portal.sql-cockpit.com',
            maxRetries: $maxRetries,
            httpClient: $this->http,
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $this->sleeps = [];
        $property = new \ReflectionProperty(Client::class, 'requester');
        $requester = $property->getValue($client);
        self::assertInstanceOf(Requester::class, $requester);
        $requester->useSleep(function (float $seconds): void {
            $this->sleeps[] = $seconds;
        });

        return $client;
    }

    /**
     * @param array<mixed>          $data
     * @param array<string, string> $headers
     */
    protected static function ok(array $data, int $status = 200, array $headers = []): Response
    {
        return self::json(['ok' => true, 'data' => $data], $status, $headers);
    }

    /**
     * @param array<string, list<string>>|null $fields
     * @param array<string, string>            $headers
     */
    protected static function error(int $status, string $code, string $message, ?array $fields = null, array $headers = []): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== null) {
            $error['fields'] = $fields;
        }

        return self::json(['ok' => false, 'error' => $error], $status, $headers);
    }

    /**
     * @param array<mixed>          $payload
     * @param array<string, string> $headers
     */
    protected static function json(array $payload, int $status = 200, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>|null
     */
    protected static function body(RequestInterface $request): ?array
    {
        $text = (string) $request->getBody();
        if ($text === '') {
            return null;
        }
        $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param array<mixed>|null $body
     */
    protected function assertRequest(string $method, string $url, ?array $body = null): RequestInterface
    {
        self::assertCount(1, $this->http->requests);
        $request = $this->http->lastRequest();
        self::assertSame($method, $request->getMethod());
        self::assertSame($url, (string) $request->getUri());
        self::assertSame('Bearer ' . self::API_KEY, $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertSame('sqlcockpit-php/' . Client::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame($body, self::body($request));
        self::assertSame($body === null ? '' : 'application/json', $request->getHeaderLine('Content-Type'));

        return $request;
    }
}
