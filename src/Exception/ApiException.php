<?php

declare(strict_types=1);

namespace SqlCockpit\Exception;

/**
 * A call to the portal failed, or was refused by the SDK before it was sent.
 *
 * $errorCode is the portal's error.code (for example "not_found") or one of the SDK's own codes:
 * "invalid_argument", "unexpected_redirect", "invalid_response", "response_too_large".
 * $status is the HTTP status, or 0 when no response was received. getCode() returns the same number.
 */
class ApiException extends \RuntimeException implements SqlCockpitException
{
    /**
     * @param array<string, list<string>>|null $fields
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 0,
        public readonly ?array $fields = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    /** The portal's error.code, or one of the SDK's own codes. */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** The HTTP status, or 0 when no response was received. */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Messages for each field that was refused, keyed by field name.
     *
     * @return array<string, list<string>>|null
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /** Seconds to wait before trying again, when the portal sent Retry-After. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
