<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * Plan usage: GET /usage.
 */
final class Usage extends Model
{
    /**
     * JSON: billing_account.
     */
    public readonly bool $billingAccount;

    /**
     * JSON: team_member_only.
     */
    public readonly bool $teamMemberOnly;

    /**
     * JSON: message.
     */
    public readonly ?string $message;

    /**
     * JSON: plan.
     */
    public readonly ?string $plan;

    /**
     * JSON: period.
     */
    public readonly ?UsagePeriod $period;

    /**
     * JSON: allowances.
     *
     * @var list<Allowance>
     */
    public readonly array $allowances;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->billingAccount = Data::bool($data, 'billing_account');
        $this->teamMemberOnly = Data::bool($data, 'team_member_only');
        $this->message = Data::nullableString($data, 'message');
        $this->plan = Data::nullableString($data, 'plan');
        $this->period = ($v = Data::object($data, 'period')) === null ? null : UsagePeriod::fromArray($v);
        $this->allowances = array_map(static fn (array $item): Allowance => Allowance::fromArray($item), Data::objects($data, 'allowances'));
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
