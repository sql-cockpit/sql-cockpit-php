<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

/**
 * Sends one request. A transport never follows redirects and stops reading a body after
 * $maxBytes, throwing TransportException::TOO_LARGE.
 *
 * @internal
 */
interface Transport
{
    /**
     * @throws TransportException
     */
    public function send(HttpRequest $request, int $maxBytes): HttpResponse;
}
