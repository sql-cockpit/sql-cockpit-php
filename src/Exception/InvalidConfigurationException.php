<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * The client was given settings it cannot use: no API key, a portal URL that is not https,
 * a timeout out of range, or no way to send HTTP requests.
 */
final class InvalidConfigurationException extends \InvalidArgumentException implements SqlCockpitException
{
}
