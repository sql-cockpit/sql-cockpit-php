<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

/**
 * One HTTP response as a transport hands it back.
 *
 * @internal
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers Header names in lower case, one line per name.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
