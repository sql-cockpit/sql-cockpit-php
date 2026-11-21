<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * 429. Too many requests. $retryAfter is the wait in seconds, when the portal sent one.
 */
final class RateLimitException extends ApiException
{
}
