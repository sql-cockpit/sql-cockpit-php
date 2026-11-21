<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * What team->resendInvite() returns.
 */
final class SentResult extends Model
{
    /**
     * JSON: sent.
     */
    public readonly bool $sent;

    /**
     * JSON: email.
     */
    public readonly string $email;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->sent = Data::bool($data, 'sent');
        $this->email = Data::string($data, 'email');
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
