<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * 400 or 422 from the portal, or an argument the SDK refused before sending. See $fields.
 */
final class ValidationException extends ApiException
{
}
