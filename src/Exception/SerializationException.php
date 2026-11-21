<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * The client holds an API key, so it cannot be serialised or unserialised.
 */
final class SerializationException extends \LogicException implements SqlCockpitException
{
}
