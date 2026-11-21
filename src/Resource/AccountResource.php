<?php

declare(strict_types=1);

namespace SqlCockpit\Resource;

use SqlCockpit\Http\Requester;
use SqlCockpit\Model\Account;
use SqlCockpit\Model\Usage;

/**
 * Your profile, plan, subscription and plan usage.
 */
final class AccountResource
{
    /**
     * @internal Use $client->account.
     */
    public function __construct(private readonly Requester $requester)
    {
    }

    /**
     * GET /account: your name, email, plan and subscription, and the credential that made the call.
     */
    public function get(): Account
    {
        return Account::fromArray($this->requester->request('GET', '/account'));
    }

    /**
     * GET /usage: what your plan includes this billing period and how much of it is used.
     */
    public function usage(): Usage
    {
        return Usage::fromArray($this->requester->request('GET', '/usage'));
    }
}
