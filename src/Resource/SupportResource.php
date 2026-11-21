<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Enum\TicketStatus;
use SqlCockpit\Http\Requester;
use SqlCockpit\Internal\Data;
use SqlCockpit\Internal\Ids;
use SqlCockpit\Model\Ticket;
use SqlCockpit\Model\TicketSummary;

/**
 * Tickets you have opened with the SQL Cockpit Team.
 */
final class SupportResource
{
    /**
     * @internal Use $client->support.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /support-tickets: the tickets you have opened, newest first.
     *
     * @param TicketStatus|string|null $status Only tickets in this status.
     *
     * @return list<TicketSummary>
     */
    public function list(TicketStatus|string|null $status = null): array
    {
        $query = ['status' => $status instanceof TicketStatus ? $status->value : $status];
        $data = $this->requester->request('GET', '/support-tickets', $query);

        return array_map(static fn (array $item): TicketSummary => TicketSummary::fromArray($item), Data::objects($data, 'tickets'));
    }

    /**
     * GET /support-tickets/{ticketId}: a ticket with its message, the Team's response and every reply.
     */
    public function get(string $ticketId): Ticket
    {
        $data = $this->requester->request('GET', '/support-tickets/' . Ids::uuid($ticketId, 'ticketId'));

        return Ticket::fromArray(Data::object($data, 'ticket') ?? []);
    }

    /**
     * POST /support-tickets: sends a ticket to the SQL Cockpit Team. A ticket that names an Agent
     * is raised as high priority.
     *
     * @param string      $subject  4 to 160 characters.
     * @param string      $message  10 to 5000 characters.
     * @param string|null $tenantId The tenant the ticket is about (a UUID).
     * @param string|null $agentId  An Agent of $tenantId (a UUID).
     */
    public function create(string $subject, string $message, ?string $tenantId = null, ?string $agentId = null): Ticket
    {
        $body = ['subject' => $subject, 'message' => $message];
        $tenantId = Ids::optionalUuid($tenantId, 'tenantId');
        $agentId = Ids::optionalUuid($agentId, 'agentId');
        if ($tenantId !== null) {
            $body['tenant_id'] = $tenantId;
        }
        if ($agentId !== null) {
            $body['agent_id'] = $agentId;
        }
        $data = $this->requester->request('POST', '/support-tickets', [], $body);

        return Ticket::fromArray(Data::object($data, 'ticket') ?? []);
    }
}
