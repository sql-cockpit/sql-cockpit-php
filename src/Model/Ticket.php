<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A support ticket with its message, the Team's response and every reply.
 *
 * support->get() fills every field. support->create() returns the summary fields plus
 * $message, which then holds the portal's confirmation rather than the ticket text.
 */
final class Ticket extends TicketSummary
{
    /**
     * JSON: message.
     */
    public readonly ?string $message;

    /**
     * JSON: agent.
     */
    public readonly ?TicketAgent $agent;

    /**
     * JSON: team_response.
     */
    public readonly ?string $teamResponse;

    /**
     * JSON: responded_at.
     */
    public readonly ?string $respondedAt;

    /**
     * JSON: resolved_at.
     */
    public readonly ?string $resolvedAt;

    /**
     * JSON: closed_at.
     */
    public readonly ?string $closedAt;

    /**
     * JSON: replies.
     *
     * @var list<TicketReply>
     */
    public readonly array $replies;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->message = Data::nullableString($data, 'message');
        $this->agent = ($v = Data::object($data, 'agent')) === null ? null : TicketAgent::fromArray($v);
        $this->teamResponse = Data::nullableString($data, 'team_response');
        $this->respondedAt = Data::nullableString($data, 'responded_at');
        $this->resolvedAt = Data::nullableString($data, 'resolved_at');
        $this->closedAt = Data::nullableString($data, 'closed_at');
        $this->replies = array_map(static fn (array $item): TicketReply => TicketReply::fromArray($item), Data::objects($data, 'replies'));
    }

    /**
     * Builds the object from the API's JSON (snake_case keys).
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
