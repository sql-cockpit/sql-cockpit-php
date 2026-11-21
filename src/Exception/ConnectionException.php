<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * The portal could not be reached (DNS, TLS, refused connection and so on).
 */
class ConnectionException extends \RuntimeException implements SqlCockpitException
{
}
