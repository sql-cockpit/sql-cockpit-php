<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * Your account: GET /account.
 */
final class Account extends Model
{
    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: email.
     */
    public readonly string $email;

    /**
     * JSON: email_verified.
     */
    public readonly bool $emailVerified;

    /**
     * JSON: billing_account.
     */
    public readonly bool $billingAccount;

    /**
     * JSON: team_member.
     */
    public readonly bool $teamMember;

    /**
     * JSON: plan. Null when you have no plan.
     */
    public readonly ?AccountPlan $plan;

    /**
     * JSON: subscription.
     */
    public readonly ?Subscription $subscription;

    /**
     * JSON: connected_via.
     */
    public readonly ?ConnectedVia $connectedVia;

    /**
     * JSON: api_key_name.
     */
    public readonly ?string $apiKeyName;

    /**
     * JSON: portal_url.
     */
    public readonly string $portalUrl;

    /**
     * JSON: billing_note.
     */
    public readonly string $billingNote;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->name = Data::string($data, 'name');
        $this->email = Data::string($data, 'email');
        $this->emailVerified = Data::bool($data, 'email_verified');
        $this->billingAccount = Data::bool($data, 'billing_account');
        $this->teamMember = Data::bool($data, 'team_member');
        $this->plan = ($v = Data::object($data, 'plan')) === null ? null : AccountPlan::fromArray($v);
        $this->subscription = ($v = Data::object($data, 'subscription')) === null ? null : Subscription::fromArray($v);
        $this->connectedVia = ($v = Data::object($data, 'connected_via')) === null ? null : ConnectedVia::fromArray($v);
        $this->apiKeyName = Data::nullableString($data, 'api_key_name');
        $this->portalUrl = Data::string($data, 'portal_url');
        $this->billingNote = Data::string($data, 'billing_note');
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
