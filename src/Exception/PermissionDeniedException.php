<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * 403. The key lacks the scope, your plan has no room, or your role cannot do this.
 */
final class PermissionDeniedException extends ApiException
{
}
