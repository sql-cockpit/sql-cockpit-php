<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

use SqlCockpit\Client;
use SqlCockpit\Exception\ApiException;
use SqlCockpit\Exception\AuthenticationException;
use SqlCockpit\Exception\ConnectionException;
use SqlCockpit\Exception\NotFoundException;
use SqlCockpit\Exception\PermissionDeniedException;
use SqlCockpit\Exception\RateLimitException;
use SqlCockpit\Exception\SerializationException;
use SqlCockpit\Exception\ServerException;
use SqlCockpit\Exception\TimeoutException;
use SqlCockpit\Exception\ValidationException;

/**
 * Sends API calls: builds the URL and headers, retries GETs on 429 and 5xx, maps errors and
 * unwraps the {"ok": true, "data": ...} envelope.
 *
 * The API key lives only inside a closure, so var_dump, print_r, var_export, json_encode and
 * serialize never show it. Exceptions are created in frames whose arguments never hold the key,
 * and a transport's own exception is never chained, because it carries the request.
 *
 * @internal
 */
final class Requester
{
    /** Responses larger than this are refused (2 MiB). */
    public const MAX_RESPONSE_BYTES = 2 * 1024 * 1024;

    /** The longest wait before a retry, in seconds, whatever Retry-After says. */
    public const MAX_RETRY_DELAY = 30.0;

    private const PATH_PATTERN = '#^/[A-Za-z0-9/_-]*$#';

    private const STATUS_CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        413 => 'payload_too_large',
        422 => 'validation_failed',
        429 => 'rate_limited',
    ];

    /** @var \Closure(): string */
    private \Closure $apiKey;

    /** @var \Closure(float): void */
    private \Closure $sleep;

    public function __construct(
        #[\SensitiveParameter] string $apiKey,
        private readonly string $baseUrl,
        private readonly Transport $transport,
        private readonly float $timeout,
        private readonly int $maxRetries,
    ) {
        $key = $apiKey;
        $apiKey = '';
        $this->apiKey = static fn (): string => $key;
        $this->sleep = static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
    }

    /**
     * Replaces how the SDK waits between retries. For tests.
     *
     * @param \Closure(float): void $sleep
     */
    public function useSleep(\Closure $sleep): void
    {
        $this->sleep = $sleep;
    }

    /**
     * Sends one call and returns the response's data.
     *
     * @param array<string, string|null> $query
     * @param array<string, mixed>|null  $body
     *
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (preg_match(self::PATH_PATTERN, $path) !== 1) {
            throw new ValidationException('Refusing to call an unexpected portal path.', 'invalid_argument');
        }

        $url = $this->baseUrl . $path;
        $query = array_filter($query, static fn (?string $value): bool => $value !== null && $value !== '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $json = null;
        if ($body !== null) {
            try {
                $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } catch (\JsonException) {
                throw new ValidationException('The request could not be encoded as JSON. Check that all text is valid UTF-8.', 'invalid_argument');
            }
        }

        $retries = $method === 'GET' ? $this->maxRetries : 0;
        for ($attempt = 0; ; ++$attempt) {
            try {
                return $this->attempt($method, $url, $json);
            } catch (RateLimitException|ServerException $error) {
                if ($attempt >= $retries) {
                    throw $error;
                }
                ($this->sleep)($this->retryDelay($error, $attempt));
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function attempt(string $method, string $url, ?string $json): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . ($this->apiKey)(),
            'User-Agent' => 'sqlcockpit-php/' . Client::VERSION,
        ];
        if ($json !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        try {
            $response = $this->transport->send(new HttpRequest($method, $url, $headers, $json, $this->timeout), self::MAX_RESPONSE_BYTES);
        } catch (TransportException $error) {
            $kind = $error->kind;
            $status = $error->getCode();
            unset($error, $headers);

            throw $this->transportError($kind, $status);
        }
        unset($headers);

        $status = $response->status;
        if ($status >= 300 && $status < 400) {
            throw new ApiException(
                'The portal answered with a redirect, which the SDK does not follow. Check portalUrl.',
                'unexpected_redirect',
                $status,
            );
        }

        $payload = null;
        $parsed = true;
        try {
            $payload = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $parsed = false;
        }

        $success = $status >= 200 && $status < 300;
        if ($success && $parsed && \is_array($payload) && ($payload['ok'] ?? null) === true) {
            $data = $payload['data'] ?? null;
            if (\is_array($data)) {
                return $data;
            }
        }
        if ($success) {
            throw new ApiException(\sprintf('The portal returned an unexpected response (HTTP %d).', $status), 'invalid_response', $status);
        }

        $error = \is_array($payload) && \is_array($payload['error'] ?? null) ? $payload['error'] : [];

        throw $this->apiError($status, $error, $response->header('Retry-After'));
    }

    /**
     * @param array<mixed> $error
     */
    private function apiError(int $status, array $error, ?string $retryAfterHeader): ApiException
    {
        $code = \is_string($error['code'] ?? null) && $error['code'] !== '' ? $error['code'] : self::codeForStatus($status);
        $message = \is_string($error['message'] ?? null) && $error['message'] !== ''
            ? $error['message']
            : \sprintf('The portal request failed (HTTP %d).', $status);
        $code = $this->redact($code);
        $message = $this->redact($message);
        $fields = $this->parseFields($error['fields'] ?? null);
        // A stack trace shows the current value of each argument, so drop the raw, unredacted error.
        $error = [];

        return match (true) {
            $status === 401 => new AuthenticationException($message, $code, $status, $fields),
            $status === 403 => new PermissionDeniedException($message, $code, $status, $fields),
            $status === 404 => new NotFoundException($message, $code, $status, $fields),
            $status === 400, $status === 422 => new ValidationException($message, $code, $status, $fields),
            $status === 429 => new RateLimitException($message, $code, $status, $fields, self::parseRetryAfter($retryAfterHeader)),
            $status >= 500 => new ServerException($message, $code, $status, $fields),
            default => new ApiException($message, $code, $status, $fields),
        };
    }

    private function transportError(string $kind, int $status): \Throwable
    {
        return match ($kind) {
            TransportException::TIMEOUT => new TimeoutException(
                \sprintf('The SQL Cockpit portal did not answer within %s seconds.', self::seconds($this->timeout))
            ),
            TransportException::TOO_LARGE => new ApiException(
                'The portal response was larger than 2 MiB and was not read.',
                'response_too_large',
                $status,
            ),
            default => new ConnectionException('Could not reach the SQL Cockpit portal. Check portalUrl and your connection.'),
        };
    }

    /**
     * @return array<string, list<string>>|null
     */
    private function parseFields(mixed $value): ?array
    {
        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            return null;
        }
        $fields = [];
        foreach ($value as $name => $messages) {
            if (!\is_array($messages)) {
                continue;
            }
            $list = [];
            foreach ($messages as $message) {
                if (\is_string($message)) {
                    $list[] = $this->redact($message);
                }
            }
            $fields[(string) $name] = $list;
        }

        return $fields;
    }

    private function redact(string $text): string
    {
        $key = ($this->apiKey)();

        return $key !== '' && str_contains($text, $key) ? str_replace($key, '[redacted]', $text) : $text;
    }

    private function retryDelay(ApiException $error, int $attempt): float
    {
        if ($error instanceof RateLimitException && $error->retryAfter !== null) {
            return min((float) $error->retryAfter, self::MAX_RETRY_DELAY);
        }

        return min(0.5 * (2 ** $attempt) + random_int(0, 250) / 1000, self::MAX_RETRY_DELAY);
    }

    /**
     * Retry-After as whole seconds: either a number of seconds or an HTTP date.
     */
    public static function parseRetryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (ctype_digit($value)) {
            return \strlen($value) > 9 ? 999_999_999 : (int) $value;
        }
        $time = strtotime($value);
        if ($time === false) {
            return null;
        }

        return max(0, $time - time());
    }

    private static function codeForStatus(int $status): string
    {
        return self::STATUS_CODES[$status] ?? ($status >= 500 ? 'server_error' : 'invalid_response');
    }

    private static function seconds(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    /**
     * What var_dump and print_r show. The API key is never included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'timeout' => $this->timeout, 'maxRetries' => $this->maxRetries];
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
}
