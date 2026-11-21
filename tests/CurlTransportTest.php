<?php

declare(strict_types=1);

namespace SqlCockpit\Tests;

use PHPUnit\Framework\TestCase;
use SqlCockpit\Client;
use SqlCockpit\Exception\ApiException;
use SqlCockpit\Exception\NotFoundException;
use SqlCockpit\Exception\TimeoutException;

/**
 * Runs the built-in cURL transport against PHP's own web server.
 */
final class CurlTransportTest extends TestCase
{
    private const API_KEY = 'sco_live_CURLKEY0123456789abcdefghijklmnop';

    /** @var resource|null */
    private static $server = null;

    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        if (!\function_exists('curl_init')) {
            return;
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            return;
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        self::$port = (int) substr($name, strrpos($name, ':') + 1);

        $command = [PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/Fixtures/router.php'];
        $null = \DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
        if (!\is_resource($process)) {
            return;
        }
        self::$server = $process;

        for ($i = 0; $i < 100; ++$i) {
            $probe = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($probe !== false) {
                fclose($probe);

                return;
            }
            usleep(50_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    protected function setUp(): void
    {
        if (!\function_exists('curl_init')) {
            self::markTestSkipped('ext-curl is not installed.');
        }
        if (self::$server === null) {
            self::markTestSkipped('The PHP built-in web server could not be started.');
        }
    }

    private function client(float $timeout = 10.0): Client
    {
        return new Client(apiKey: self::API_KEY, portalUrl: 'http://127.0.0.1:' . self::$port, timeout: $timeout, maxRetries: 0);
    }

    public function testGet(): void
    {
        $account = $this->client()->account->get();

        self::assertSame('Ada', $account->name);
        $seen = $account->toArray()['seen'] ?? null;
        self::assertIsArray($seen);
        self::assertSame('GET', $seen['method']);
        self::assertSame('Bearer ' . self::API_KEY, $seen['authorization']);
        self::assertSame('sqlcockpit-php/' . Client::VERSION, $seen['user_agent']);
    }

    public function testPostSendsJson(): void
    {
        $ticket = $this->client()->support->create(subject: 'Help please', message: 'Something is wrong here.');

        self::assertSame('application/json', $ticket->subject);
        self::assertSame('{"subject":"Help please","message":"Something is wrong here."}', $ticket->message);
    }

    public function testRedirectIsNotFollowed(): void
    {
        try {
            $this->client()->account->usage();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('unexpected_redirect', $error->errorCode);
            self::assertSame(302, $error->status);
        }
    }

    public function testLargeResponseIsRefused(): void
    {
        try {
            $this->client()->tenants->list();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('response_too_large', $error->errorCode);
        }
    }

    public function testErrorStatus(): void
    {
        $this->expectException(NotFoundException::class);
        $this->client()->agents->list();
    }

    public function testTimeout(): void
    {
        $this->expectException(TimeoutException::class);
        $this->client(timeout: 0.5)->instances->list();
    }
}
