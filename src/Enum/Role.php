<?php

declare(strict_types=1);

namespace SqlCockpit\Enum;

/**
 * Every role a tenant member can hold.
 */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Developer = 'developer';
    case Operator = 'operator';
    case ReadOnly = 'read-only';
}
