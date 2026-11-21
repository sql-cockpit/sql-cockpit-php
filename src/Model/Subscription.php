<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * Your subscription.
 */
final class Subscription extends Model
{
    /**
     * JSON: status.
     */
    public readonly string $status;

    /**
     * JSON: cycle. "month" or "year".
     */
    public readonly string $cycle;

    /**
     * JSON: started_at.
     */
    public readonly ?string $startedAt;

    /**
     * JSON: trial_ends_at.
     */
    public readonly ?string $trialEndsAt;

    /**
     * JSON: ends_at.
     */
    public readonly ?string $endsAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->status = Data::string($data, 'status');
        $this->cycle = Data::string($data, 'cycle');
        $this->startedAt = Data::nullableString($data, 'started_at');
        $this->trialEndsAt = Data::nullableString($data, 'trial_ends_at');
        $this->endsAt = Data::nullableString($data, 'ends_at');
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
