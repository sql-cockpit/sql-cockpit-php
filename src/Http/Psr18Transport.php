<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Sends requests through a PSR-18 client. The client's own exceptions are never passed on:
 * they carry the request, and so the API key.
 *
 * @internal
 */
final class Psr18Transport implements Transport
{
    private const CHUNK_BYTES = 8192;

    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function send(HttpRequest $request, int $maxBytes): HttpResponse
    {
        try {
            $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);
            foreach ($request->headers as $name => $value) {
                $psrRequest = $psrRequest->withHeader($name, $value);
            }
            if ($request->body !== null) {
                $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
            }
        } catch (\Throwable) {
            throw new TransportException(TransportException::CONNECTION);
        }

        try {
            $response = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $error) {
            $timedOut = preg_match('/timed?[\s_-]?out/i', $error->getMessage()) === 1;
            throw new TransportException($timedOut ? TransportException::TIMEOUT : TransportException::CONNECTION);
        } catch (\Throwable) {
            throw new TransportException(TransportException::CONNECTION);
        }

        $status = $response->getStatusCode();
        $headers = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new HttpResponse($status, $headers, $this->readBody($response, $status, $maxBytes));
    }

    private function readBody(ResponseInterface $response, int $status, int $maxBytes): string
    {
        $declared = $response->getHeaderLine('Content-Length');
        if ($declared !== '' && ctype_digit($declared) && (int) $declared > $maxBytes) {
            $this->close($response);
            throw new TransportException(TransportException::TOO_LARGE, $status);
        }

        try {
            $stream = $response->getBody();
            $size = $stream->getSize();
            if ($size !== null && $size > $maxBytes) {
                $this->close($response);
                throw new TransportException(TransportException::TOO_LARGE, $status);
            }
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            $body = '';
            while (!$stream->eof()) {
                $chunk = $stream->read(self::CHUNK_BYTES);
                if ($chunk === '') {
                    break;
                }
                $body .= $chunk;
                if (\strlen($body) > $maxBytes) {
                    $this->close($response);
                    throw new TransportException(TransportException::TOO_LARGE, $status);
                }
            }
        } catch (TransportException $error) {
            throw $error;
        } catch (\Throwable) {
            throw new TransportException(TransportException::CONNECTION);
        }

        return $body;
    }

    private function close(ResponseInterface $response): void
    {
        try {
            $response->getBody()->close();
        } catch (\Throwable) {
            // Nothing to clean up.
        }
    }
}
