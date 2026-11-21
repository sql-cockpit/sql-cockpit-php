<?php

declare(strict_types=1);

namespace SqlCockpit\Enum;

/**
 * The status of a support ticket.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Sent = 'sent';
    case WaitingOnCustomer = 'waiting_on_customer';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
