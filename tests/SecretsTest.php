<?php

declare(strict_types=1);

namespace SqlCockpit\Tests;

use SqlCockpit\Exception\ApiException;
use SqlCockpit\Exception\SerializationException;
use SqlCockpit\Exception\SqlCockpitException;
use SqlCockpit\Tests\Support\ClientTestCase;

/**
 * The API key must never show up in an exception, a dump or a serialised value.
 */
final class SecretsTest extends ClientTestCase
{
    public function testClientDumpsNeverShowTheKey(): void
    {
        $client = $this->client();

        $this->assertNoKey($this->dump($client), 'var_dump');
        $this->assertNoKey(print_r($client, true), 'print_r');
        $this->assertNoKey(var_export($client, true), 'var_export');
        $this->assertNoKey((string) json_encode($client), 'json_encode');
        self::assertSame(
            ['portalUrl' => 'https://portal.sql-cockpit.com', 'timeout' => 20.0, 'maxRetries' => 2],
            $client->__debugInfo(),
        );
        self::assertSame('{"portalUrl":"https:\/\/portal.sql-cockpit.com","timeout":20.0,"maxRetries":2}', json_encode($client, JSON_PRESERVE_ZERO_FRACTION));
        foreach ([$client->account, $client->apiKeys, $client->tenants, $client->team, $client->agents, $client->instances, $client->support] as $resource) {
            $this->assertNoKey($this->dump($resource), 'var_dump of a resource');
            $this->assertNoKey(print_r($resource, true), 'print_r of a resource');
            $this->assertNoKey(var_export($resource, true), 'var_export of a resource');
            $this->assertNoKey((string) json_encode($resource), 'json_encode of a resource');
        }
    }

    public function testClientCannotBeSerialised(): void
    {
        $client = $this->client();

        try {
            serialize($client);
            self::fail('The client was serialised.');
        } catch (SerializationException $error) {
            self::assertInstanceOf(SqlCockpitException::class, $error);
            $this->assertNoKey($error->getMessage(), 'the message');
        }

        $this->expectException(SerializationException::class);
        serialize($client->team);
    }

    public function testClientCannotBeUnserialised(): void
    {
        $this->expectException(SerializationException::class);
        unserialize('O:17:"SqlCockpit\\Client":0:{}');
    }

    public function testApiErrorThatEchoesTheKeyIsRedacted(): void
    {
        $client = $this->client([self::error(401, 'unauthenticated', 'Key ' . self::API_KEY . ' is revoked.', ['key' => ['Bad ' . self::API_KEY]])]);

        try {
            $client->account->get();
            self::fail('No exception was thrown.');
        } catch (ApiException $error) {
            self::assertSame('Key [redacted] is revoked.', $error->getMessage());
            self::assertSame(['key' => ['Bad [redacted]']], $error->fields);
        }
    }

    /**
     * Every kind of failure, dumped every way, in a separate PHP process with stack trace
     * arguments turned on, so no test-runner state (which holds the fake client and the
     * requests it saw) can show up in a trace.
     */
    public function testExceptionsNeverCarryTheKey(): void
    {
        $command = [PHP_BINARY, '-d', 'zend.exception_ignore_args=0', '-d', 'memory_limit=1G', __DIR__ . '/Fixtures/leak-check.php', self::API_KEY];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);

        self::assertStringContainsString('ALL-DONE', $output, $errors);
        self::assertSame(17, substr_count($output, 'SCENARIO-DONE'));
        self::assertStringNotContainsString('no exception', $output);
        self::assertStringNotContainsString('previous: chained', $output);
        self::assertStringContainsString('[redacted]', $output);
        $this->assertNoKey($output, 'an exception or a dump');
    }

    private function assertNoKey(string $text, string $where): void
    {
        self::assertStringNotContainsString(self::API_KEY, $text, "The API key leaked through $where.");
        self::assertStringNotContainsString('TESTKEY0123456789', $text, "Part of the API key leaked through $where.");
    }

    private function dump(mixed $value): string
    {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }
}
