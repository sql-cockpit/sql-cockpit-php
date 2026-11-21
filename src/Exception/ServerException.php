<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * 5xx. The portal failed. GET requests are retried before this is thrown.
 */
final class ServerException extends ApiException
{
}
