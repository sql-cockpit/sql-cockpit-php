<?php

declare(strict_types=1);

namespace SqlCockpit\Enum;

/**
 * The roles you can give someone. The owner role cannot be assigned.
 */
enum AssignableRole: string
{
    case Admin = 'admin';
    case Developer = 'developer';
    case Operator = 'operator';
    case ReadOnly = 'read-only';
}
