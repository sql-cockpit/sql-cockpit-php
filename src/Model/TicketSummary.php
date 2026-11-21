<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A support ticket as listed by support->list().
 */
class TicketSummary extends Model
{
    /**
     * JSON: id.
     */
    public readonly string $id;

    /**
     * JSON: subject.
     */
    public readonly string $subject;

    /**
     * JSON: status. One of the TicketStatus values.
     */
    public readonly string $status;

    /**
     * JSON: status_label.
     */
    public readonly string $statusLabel;

    /**
     * JSON: priority. "normal" or "high".
     */
    public readonly string $priority;

    /**
     * JSON: tenant.
     */
    public readonly ?TenantRef $tenant;

    /**
     * JSON: created_at.
     */
    public readonly string $createdAt;

    /**
     * JSON: updated_at.
     */
    public readonly string $updatedAt;

    /**
     * JSON: portal_url.
     */
    public readonly string $portalUrl;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->id = Data::string($data, 'id');
        $this->subject = Data::string($data, 'subject');
        $this->status = Data::string($data, 'status');
        $this->statusLabel = Data::string($data, 'status_label');
        $this->priority = Data::string($data, 'priority');
        $this->tenant = ($v = Data::object($data, 'tenant')) === null ? null : TenantRef::fromArray($v);
        $this->createdAt = Data::string($data, 'created_at');
        $this->updatedAt = Data::string($data, 'updated_at');
        $this->portalUrl = Data::string($data, 'portal_url');
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
