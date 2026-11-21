<?php

declare(strict_types=1);

namespace SqlCockpit\Tests;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use SqlCockpit\Exception\ApiException;
use SqlCockpit\Exception\AuthenticationException;
use SqlCockpit\Exception\ConnectionException;
use SqlCockpit\Exception\NotFoundException;
use SqlCockpit\Exception\PermissionDeniedException;
use SqlCockpit\Exception\RateLimitException;
use SqlCockpit\Exception\ServerException;
use SqlCockpit\Exception\SqlCockpitException;
use SqlCockpit\Exception\TimeoutException;
use SqlCockpit\Exception\ValidationException;
use SqlCockpit\Http\Requester;
use SqlCockpit\Tests\Support\ClientTestCase;
use SqlCockpit\Tests\Support\FakeNetworkException;

final class ErrorsTest extends ClientTestCase
{
    /**
     * @return iterable<string, array{int, string, class-string<ApiException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, 'bad_request', ValidationException::class];
        yield '401' => [401, 'unauthenticated', AuthenticationException::class];
        yield '403' => [403, 'insufficient_scope', PermissionDeniedException::class];
        yield '404' => [404, 'not_found', NotFoundException::class];
        yield '405' => [405, 'method_not_allowed', ApiException::class];
        yield '413' => [413, 'payload_too_large', ApiException::class];
        yield '422' => [422, 'validation_failed', ValidationException::class];
        yield '429' => [429, 'rate_limited', RateLimitException::class];
        yield '500' => [500, 'server_error', ServerException::class];
        yield '503' => [503, 'server_error', ServerException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('statuses')]
    public function testStatusMapsToException(int $status, string $code, string $class): void
    {
        // A POST is never retried, so one response is enough for every status.
        $client = $this->client([self::error($status, $code, 'Nope.')]);

        try {
            $client->support->create(subject: 'Help please', message: 'Something is wrong here.');
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame($class, $error::class);
            self::assertInstanceOf(SqlCockpitException::class, $error);
            self::assertSame($code, $error->errorCode);
            self::assertSame($code, $error->getErrorCode());
            self::assertSame($status, $error->status);
            self::assertSame($status, $error->getStatus());
            self::assertSame($status, $error->getCode());
            self::assertSame('Nope.', $error->getMessage());
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testValidationFields(): void
    {
        $client = $this->client([self::error(422, 'validation_failed', 'Check the fields.', ['email' => ['Must be a valid email.']])]);

        try {
            $client->team->add(self::TENANT, name: 'Sam', email: 'nope', role: 'developer');
            self::fail('No exception was thrown.');
        } catch (ValidationException $error) {
            self::assertSame(['email' => ['Must be a valid email.']], $error->fields);
            self::assertSame(['email' => ['Must be a valid email.']], $error->getFields());
            self::assertNull($error->retryAfter);
        }
    }

    public function testErrorWithoutEnvelopeUsesTheStatus(): void
    {
        $client = $this->client([new Response(404, [], 'Not Found')]);

        try {
            $client->support->create(subject: 'Help please', message: 'Something is wrong here.');
            self::fail('No exception was thrown.');
        } catch (NotFoundException $error) {
            self::assertSame('not_found', $error->errorCode);
            self::assertSame('The portal request failed (HTTP 404).', $error->getMessage());
            self::assertNull($error->fields);
        }
    }

    public function testRateLimitCarriesRetryAfter(): void
    {
        $client = $this->client([self::error(429, 'rate_limited', 'Slow down.', null, ['Retry-After' => '17'])]);

        try {
            $client->support->create(subject: 'Help please', message: 'Something is wrong here.');
            self::fail('No exception was thrown.');
        } catch (RateLimitException $error) {
            self::assertSame(17, $error->retryAfter);
            self::assertSame(17, $error->getRetryAfter());
        }
    }

    public function testRetryAfterAsHttpDate(): void
    {
        self::assertSame(0, Requester::parseRetryAfter('Wed, 21 Oct 2015 07:28:00 GMT'));
        $soon = Requester::parseRetryAfter(gmdate('D, d M Y H:i:s', time() + 60) . ' GMT');
        self::assertNotNull($soon);
        self::assertGreaterThanOrEqual(58, $soon);
        self::assertLessThanOrEqual(60, $soon);
        self::assertNull(Requester::parseRetryAfter('soon'));
        self::assertNull(Requester::parseRetryAfter(null));
        self::assertSame(5, Requester::parseRetryAfter(' 5 '));
    }

    public function testGetRetriesOn5xxThenSucceeds(): void
    {
        $client = $this->client([
            self::error(503, 'server_error', 'Busy.'),
            self::error(502, 'server_error', 'Busy.'),
            self::ok(['tenants' => []]),
        ]);

        self::assertSame([], $client->tenants->list());
        self::assertCount(3, $this->http->requests);
        self::assertCount(2, $this->sleeps);
        self::assertGreaterThanOrEqual(0.5, $this->sleeps[0]);
        self::assertGreaterThanOrEqual(1.0, $this->sleeps[1]);
    }

    public function testGetGivesUpAfterTwoRetries(): void
    {
        $client = $this->client([
            self::error(500, 'server_error', 'Down.'),
            self::error(500, 'server_error', 'Down.'),
            self::error(500, 'server_error', 'Still down.'),
            self::ok(['tenants' => []]),
        ]);

        try {
            $client->tenants->list();
            self::fail('No exception was thrown.');
        } catch (ServerException $error) {
            self::assertSame('Still down.', $error->getMessage());
        }
        self::assertCount(3, $this->http->requests);
    }

    public function testGetRetriesOn429HonouringRetryAfterCappedAt30Seconds(): void
    {
        $client = $this->client([
            self::error(429, 'rate_limited', 'Slow down.', null, ['Retry-After' => '3']),
            self::error(429, 'rate_limited', 'Slow down.', null, ['Retry-After' => '120']),
            self::ok(['name' => 'Ada']),
        ]);

        self::assertSame('Ada', $client->account->get()->name);
        self::assertSame([3.0, 30.0], $this->sleeps);
    }

    public function testMaxRetriesZeroDoesNotRetry(): void
    {
        $client = $this->client([self::error(503, 'server_error', 'Busy.'), self::ok([])], maxRetries: 0);

        $this->expectException(ServerException::class);
        try {
            $client->account->get();
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testGetDoesNotRetryOtherErrors(): void
    {
        $client = $this->client([self::error(404, 'not_found', 'No.'), self::ok([])]);

        $this->expectException(NotFoundException::class);
        try {
            $client->team->list(self::TENANT);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writes(): iterable
    {
        yield 'POST add' => ['add'];
        yield 'PATCH changeRole' => ['changeRole'];
        yield 'DELETE remove' => ['remove'];
        yield 'POST resendInvite' => ['resendInvite'];
        yield 'POST create ticket' => ['create'];
    }

    #[DataProvider('writes')]
    public function testWritesAreNeverRetried(string $operation): void
    {
        $client = $this->client([
            self::error(503, 'server_error', 'Busy.'),
            self::error(429, 'rate_limited', 'Slow.', null, ['Retry-After' => '0']),
            self::ok([]),
        ]);

        try {
            match ($operation) {
                'add' => $client->team->add(self::TENANT, name: 'Sam Lee', email: 'sam@example.com', role: 'developer'),
                'changeRole' => $client->team->changeRole(self::TENANT, self::MEMBER, role: 'admin'),
                'remove' => $client->team->remove(self::TENANT, self::MEMBER),
                'resendInvite' => $client->team->resendInvite(self::TENANT, self::MEMBER),
                default => $client->support->create(subject: 'Help please', message: 'Something is wrong here.'),
            };
            self::fail('No exception was thrown.');
        } catch (ServerException) {
            self::assertCount(1, $this->http->requests);
            self::assertSame([], $this->sleeps);
        }
    }

    public function testRedirectIsRefusedAndNotRetried(): void
    {
        $client = $this->client([
            new Response(302, ['Location' => 'https://evil.example.com/steal']),
            self::ok([]),
        ]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('unexpected_redirect', $error->errorCode);
            self::assertSame(302, $error->status);
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testResponseOverTwoMebibytesIsRefused(): void
    {
        $body = '{"ok":true,"data":{"name":"' . str_repeat('a', Requester::MAX_RESPONSE_BYTES) . '"}}';
        $client = $this->client([new Response(200, [], Stream::create($body))]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('response_too_large', $error->errorCode);
            self::assertSame(200, $error->status);
        }
    }

    public function testDeclaredContentLengthOverTheCapIsRefused(): void
    {
        $client = $this->client([new Response(200, ['Content-Length' => (string) (Requester::MAX_RESPONSE_BYTES + 1)], '{}')]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('response_too_large', $error->errorCode);
        }
    }

    public function testResponseAtTheCapIsRead(): void
    {
        $prefix = '{"ok":true,"data":{"name":"';
        $suffix = '"}}';
        $name = str_repeat('a', Requester::MAX_RESPONSE_BYTES - \strlen($prefix) - \strlen($suffix));
        $client = $this->client([new Response(200, [], $prefix . $name . $suffix)]);

        self::assertSame(\strlen($name), \strlen($client->account->get()->name));
    }

    public function testInvalidJsonOnSuccessIsInvalidResponse(): void
    {
        $client = $this->client([new Response(200, [], '<html>hello</html>')]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame(ApiException::class, $error::class);
            self::assertSame('invalid_response', $error->errorCode);
        }
    }

    public function testNonJson5xxIsServerError(): void
    {
        $client = $this->client([new Response(502, [], 'Bad Gateway')], maxRetries: 0);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ServerException $error) {
            self::assertSame('server_error', $error->errorCode);
            self::assertSame(502, $error->status);
        }
    }

    public function testNetworkFailureIsConnectionException(): void
    {
        $request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createRequest('GET', self::BASE)->withHeader('Authorization', 'Bearer ' . self::API_KEY);
        $client = $this->client([new FakeNetworkException('Could not resolve host.', $request)]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ConnectionException $error) {
            self::assertSame(ConnectionException::class, $error::class);
            self::assertInstanceOf(SqlCockpitException::class, $error);
            self::assertNull($error->getPrevious());
        }
    }

    public function testTimeoutIsTimeoutException(): void
    {
        $request = (new \Nyholm\Psr7\Factory\Psr17Factory())->createRequest('GET', self::BASE);
        $client = $this->client([new FakeNetworkException('cURL error 28: Operation timed out', $request)]);

        $this->expectException(TimeoutException::class);
        $client->account->get();
    }
}
