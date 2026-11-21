<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * 401. The API key is missing, wrong, revoked or expired.
 */
final class AuthenticationException extends ApiException
{
}
