<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

/**
 * The built-in transport, used when no PSR-18 client the SDK can configure is installed.
 * It needs ext-curl. It never follows redirects, speaks only http and https, verifies TLS
 * and stops downloading once a body passes the size cap.
 *
 * @internal
 */
final class CurlTransport implements Transport
{
    public static function isAvailable(): bool
    {
        return \function_exists('curl_init');
    }

    public function send(HttpRequest $request, int $maxBytes): HttpResponse
    {
        $url = $request->url;
        $method = $request->method;
        if ($url === '' || $method === '') {
            throw new TransportException(TransportException::CONNECTION);
        }

        $handle = curl_init();
        if ($handle === false) {
            throw new TransportException(TransportException::CONNECTION);
        }

        $headerLines = ['Expect:'];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $status = 0;
        $headers = [];
        $body = '';
        $tooLarge = false;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT_MS => max(1, (int) ceil($request->timeout * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) ceil($request->timeout * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$status, &$headers, &$tooLarge, $maxBytes): int {
                $trimmed = trim($line);
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trimmed, $match) === 1) {
                    // A new header block (after 100 Continue, for example) starts over.
                    $status = (int) $match[1];
                    $headers = [];
                } elseif (str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $name = strtolower(trim($name));
                    $value = trim($value);
                    if (!isset($headers[$name])) {
                        $headers[$name] = $value;
                    }
                    if ($name === 'content-length' && ctype_digit($value) && (int) $value > $maxBytes) {
                        $tooLarge = true;

                        return 0;
                    }
                }

                return \strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (\strlen($body) + \strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0;
                }
                $body .= $chunk;

                return \strlen($chunk);
            },
        ];
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        } elseif ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = '';
        }

        if (!curl_setopt_array($handle, $options)) {
            throw new TransportException(TransportException::CONNECTION);
        }

        $ok = curl_exec($handle);
        $errno = curl_errno($handle);
        unset($handle);

        if ($tooLarge) {
            throw new TransportException(TransportException::TOO_LARGE, $status);
        }
        if ($ok === false || $errno !== 0) {
            throw new TransportException($errno === CURLE_OPERATION_TIMEDOUT ? TransportException::TIMEOUT : TransportException::CONNECTION);
        }

        /** @var array<string, string> $headers */
        return new HttpResponse($status, $headers, $body);
    }
}
