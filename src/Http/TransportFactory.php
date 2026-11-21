<?php

declare(strict_types=1);

namespace SqlCockpit\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SqlCockpit\Exception\InvalidConfigurationException;

/**
 * Picks the transport.
 *
 * - A PSR-18 client you pass is used as it is. PSR-17 factories are discovered when you do not pass them.
 * - Otherwise php-http/discovery looks for an installed PSR-18 client. The SDK uses it only when it
 *   knows how to set the timeout and turn redirects off: Symfony HttpClient and Guzzle 7.
 * - Otherwise the built-in cURL transport is used.
 *
 * @internal
 */
final class TransportFactory
{
    private const SYMFONY_PSR18 = 'Symfony\Component\HttpClient\Psr18Client';
    private const SYMFONY_HTTP_CLIENT = 'Symfony\Component\HttpClient\HttpClient';
    private const GUZZLE = 'GuzzleHttp\Client';

    public static function create(
        ?ClientInterface $client,
        ?RequestFactoryInterface $requestFactory,
        ?StreamFactoryInterface $streamFactory,
        float $timeout,
    ): Transport {
        if ($client !== null) {
            [$requestFactory, $streamFactory] = self::factories($requestFactory, $streamFactory);
            if ($requestFactory === null || $streamFactory === null) {
                throw new InvalidConfigurationException(
                    'No PSR-17 request and stream factories were found for your HTTP client. '
                    . 'Install one (for example nyholm/psr7) or pass requestFactory and streamFactory.'
                );
            }

            return new Psr18Transport($client, $requestFactory, $streamFactory);
        }

        $discovered = self::discover($timeout);
        if ($discovered !== null) {
            [$requestFactory, $streamFactory] = self::factories($requestFactory, $streamFactory);
            if ($requestFactory !== null && $streamFactory !== null) {
                return new Psr18Transport($discovered, $requestFactory, $streamFactory);
            }
        }

        if (CurlTransport::isAvailable()) {
            return new CurlTransport();
        }

        throw new InvalidConfigurationException(
            'No way to send HTTP requests was found. Enable the curl extension, install a PSR-18 client '
            . '(symfony/http-client or guzzlehttp/guzzle), or pass httpClient.'
        );
    }

    /**
     * A discovered PSR-18 client, set up with the timeout and without redirects, or null.
     */
    private static function discover(float $timeout): ?ClientInterface
    {
        try {
            $found = Psr18ClientDiscovery::find();
        } catch (\Throwable) {
            return null;
        }

        try {
            if (is_a($found, self::SYMFONY_PSR18) && class_exists(self::SYMFONY_HTTP_CLIENT)) {
                $factory = [self::SYMFONY_HTTP_CLIENT, 'create'];
                $symfonyClass = self::SYMFONY_PSR18;
                if (\is_callable($factory)) {
                    $client = self::instantiate($symfonyClass, [$factory([
                        'timeout' => $timeout,
                        'max_duration' => $timeout,
                        'max_redirects' => 0,
                    ])]);

                    return $client instanceof ClientInterface ? $client : null;
                }
            }
            if (is_a($found, self::GUZZLE)) {
                $guzzleClass = self::GUZZLE;
                $client = self::instantiate($guzzleClass, [[
                    'timeout' => $timeout,
                    'connect_timeout' => $timeout,
                    'allow_redirects' => false,
                    'http_errors' => false,
                ]]);

                return $client instanceof ClientInterface ? $client : null;
            }
        } catch (\Throwable) {
            return null;
        }

        // Some other client: its redirect and timeout settings are unknown, so it is not used.
        return null;
    }

    /**
     * @param list<mixed> $arguments
     */
    private static function instantiate(string $class, array $arguments): object
    {
        return new $class(...$arguments);
    }

    /**
     * @return array{0: ?RequestFactoryInterface, 1: ?StreamFactoryInterface}
     */
    private static function factories(?RequestFactoryInterface $requestFactory, ?StreamFactoryInterface $streamFactory): array
    {
        try {
            $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        } catch (\Throwable) {
            $requestFactory = null;
        }
        try {
            $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        } catch (\Throwable) {
            $streamFactory = null;
        }

        return [$requestFactory, $streamFactory];
    }
}
