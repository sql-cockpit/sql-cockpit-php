<?php

// Provokes every kind of failure and prints each exception through every dump function.
// SecretsTest runs this in its own PHP process (with zend.exception_ignore_args=0), so no
// test-runner state can show up in a stack trace, and checks that the key never appears.

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use SqlCockpit\Client;
use SqlCockpit\Tests\Support\FakeHttpClient;
use SqlCockpit\Tests\Support\FakeNetworkException;

$key = $argv[1] ?? '';
$base = 'https://portal.sql-cockpit.com/api/portal/v1';
$tenant = '11111111-2222-4333-8444-555555555555';

function report(string $name, Throwable $error): void
{
    echo "== $name: ", $error::class, "\n";
    echo $error->getMessage(), "\n";
    echo (string) $error, "\n";
    var_dump($error);
    echo print_r($error, true), "\n";
    echo json_encode($error), "\n";
    try {
        echo serialize($error), "\n";
    } catch (Throwable $serializeError) {
        echo 'serialize refused: ', $serializeError->getMessage(), "\n";
    }
    echo 'previous: ', $error->getPrevious() === null ? 'none' : 'chained', "\n";
    echo "SCENARIO-DONE\n";
}

/**
 * @param list<Response|Throwable> $responses
 */
function makeClient(string $key, array $responses): Client
{
    $factory = new Psr17Factory();
    $client = new Client(apiKey: $key, httpClient: new FakeHttpClient($responses), requestFactory: $factory, streamFactory: $factory);
    $requester = (new ReflectionProperty(Client::class, 'requester'))->getValue($client);
    if ($requester instanceof SqlCockpit\Http\Requester) {
        $requester->useSleep(static function (float $seconds): void {
        });
    }

    return $client;
}

function errorResponse(int $status, string $message, string $key): Response
{
    return new Response($status, [], (string) json_encode(['ok' => false, 'error' => [
        'code' => 'x',
        'message' => $message,
        'fields' => ['key' => ['Bad ' . $key]],
    ]]));
}

$request = (new Psr17Factory())->createRequest('GET', $base)->withHeader('Authorization', 'Bearer ' . $key);
$scenarios = [
    'echoed key' => [errorResponse(401, "Key $key is revoked.", $key)],
    'network' => [new FakeNetworkException('Could not connect.', $request)],
    'timeout' => [new FakeNetworkException('Operation timed out.', $request)],
    'redirect' => [new Response(302, ['Location' => 'https://evil.example.com'])],
    'too large' => [new Response(200, [], str_repeat('x', 3 * 1024 * 1024))],
    'invalid json' => [new Response(200, [], 'not json')],
    'forbidden' => [errorResponse(403, 'No.', $key)],
    'server after retries' => [errorResponse(500, 'Down.', $key), errorResponse(500, 'Down.', $key), errorResponse(500, 'Down.', $key)],
    'rate limited' => [errorResponse(429, 'Slow.', $key), errorResponse(429, 'Slow.', $key), errorResponse(429, 'Slow.', $key)],
];
foreach ($scenarios as $name => $responses) {
    $client = makeClient($key, $responses);
    try {
        $client->tenants->list();
        echo "== $name: no exception\n";
    } catch (Throwable $error) {
        report($name, $error);
    }
}

$client = makeClient($key, []);
try {
    $client->team->add('bad id', name: 'Sam Lee', email: 'sam@example.com', role: 'developer');
} catch (Throwable $error) {
    report('bad id', $error);
}
try {
    $client->support->create(subject: "Bad \xB1", message: 'Something is wrong here.', tenantId: $tenant);
} catch (Throwable $error) {
    report('bad utf-8', $error);
}
try {
    serialize($client);
} catch (Throwable $error) {
    report('serialize client', $error);
}

$configAttempts = [
    'http url' => static fn () => new Client(apiKey: $key, portalUrl: 'http://portal.sql-cockpit.com', httpClient: new FakeHttpClient()),
    'url with credentials' => static fn () => new Client(apiKey: $key, portalUrl: 'https://u:p@portal.sql-cockpit.com', httpClient: new FakeHttpClient()),
    'bad timeout' => static fn () => new Client(apiKey: $key, timeout: 0.0, httpClient: new FakeHttpClient()),
    'bad retries' => static fn () => new Client(apiKey: $key, maxRetries: 9, httpClient: new FakeHttpClient()),
    'malformed key' => static fn () => new Client(apiKey: $key . ' with spaces', httpClient: new FakeHttpClient()),
];
foreach ($configAttempts as $name => $attempt) {
    try {
        $attempt();
        echo "== $name: no exception\n";
    } catch (Throwable $error) {
        report($name, $error);
    }
}

$client = makeClient($key, []);
var_dump($client);
echo print_r($client, true), "\n";
echo var_export($client, true), "\n";
echo json_encode($client), "\n";
echo "ALL-DONE\n";
