<?php

declare(strict_types=1);

namespace SqlCockpit\Tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers from a queue and records every request it receives.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $queue;

    /**
     * @param list<ResponseInterface|\Throwable> $queue
     */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('The fake HTTP client has no response left.');
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('No request was sent.');
        }

        return $last;
    }
}
