<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * One reply on a ticket.
 */
final class TicketReply extends Model
{
    /**
     * JSON: from. "you" or "sql_cockpit_team".
     */
    public readonly string $from;

    /**
     * JSON: body.
     */
    public readonly string $body;

    /**
     * JSON: created_at.
     */
    public readonly string $createdAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->from = Data::string($data, 'from');
        $this->body = Data::string($data, 'body');
        $this->createdAt = Data::string($data, 'created_at');
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
