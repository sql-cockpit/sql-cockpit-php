<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

/**
 * A transport failure. The Requester turns it into a public exception and drops this one,
 * so the request (and the API key in it) never reaches the caller.
 *
 * @internal
 */
final class TransportException extends \RuntimeException
{
    public const CONNECTION = 'connection';
    public const TIMEOUT = 'timeout';
    public const TOO_LARGE = 'too_large';
    public const TLS = 'tls';

    public function __construct(public readonly string $kind, int $status = 0)
    {
        parent::__construct($kind, $status);
    }
}
