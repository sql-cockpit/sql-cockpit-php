<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

use SqlCockpit\Exception\SerializationException;

/**
 * One HTTP request as the SDK hands it to a transport.
 *
 * @internal
 */
final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        #[\SensitiveParameter] public readonly array $headers,
        public readonly ?string $body,
        public readonly float $timeout,
    ) {
    }

    /**
     * What var_dump and print_r show. The Authorization header is never included.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'method' => $this->method,
            'url' => $this->url,
            'headers' => self::redactHeaders($this->headers),
            'timeout' => $this->timeout,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new SerializationException('An HTTP request of the SQL Cockpit SDK cannot be serialised.');
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new SerializationException('An HTTP request of the SQL Cockpit SDK cannot be unserialised.');
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function redactHeaders(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $headers[$name] = '[redacted]';
            }
        }

        return $headers;
    }
}
