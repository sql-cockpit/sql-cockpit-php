<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * What team->add() and team->changeRole() return.
 */
final class MemberResult extends Model
{
    /**
     * JSON: member.
     */
    public readonly Member $member;

    /**
     * JSON: message.
     */
    public readonly string $message;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->member = Member::fromArray(Data::object($data, 'member') ?? []);
        $this->message = Data::string($data, 'message');
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
