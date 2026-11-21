<?php

declare(strict_types=1);

namespace SqlCockpit\Tests\Support;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * A PSR-18 network failure that, like real ones, carries the request (and so the API key).
 */
final class FakeNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message . ' ' . $request->getHeaderLine('Authorization'));
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
