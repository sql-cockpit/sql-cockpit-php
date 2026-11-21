<?php

declare(strict_types=1);

namespace SqlCockpit\Enum;

/**
 * The status of an Agent.
 */
enum AgentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Revoked = 'revoked';
    case Suspended = 'suspended';
}
