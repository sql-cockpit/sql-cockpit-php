<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * The latest command an Agent ran.
 */
final class AgentCommand extends Model
{
    /**
     * JSON: type.
     */
    public readonly string $type;

    /**
     * JSON: status.
     */
    public readonly string $status;

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
        $this->type = Data::string($data, 'type');
        $this->status = Data::string($data, 'status');
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
