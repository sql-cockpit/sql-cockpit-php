<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * The portal did not answer within the client's timeout.
 */
final class TimeoutException extends ConnectionException
{
}
