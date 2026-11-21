<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * A tenant you own or belong to.
 */
final class Tenant extends Model
{
    /**
     * JSON: id.
     */
    public readonly string $id;

    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: slug.
     */
    public readonly string $slug;

    /**
     * JSON: subdomain.
     */
    public readonly ?string $subdomain;

    /**
     * JSON: domain.
     */
    public readonly ?string $domain;

    /**
     * JSON: status.
     */
    public readonly string $status;

    /**
     * JSON: your_role. One of the Role values.
     */
    public readonly string $yourRole;

    /**
     * JSON: is_owner.
     */
    public readonly bool $isOwner;

    /**
     * JSON: abilities.
     *
     * @var list<string>
     */
    public readonly array $abilities;

    /**
     * JSON: agents_count.
     */
    public readonly int $agentsCount;

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
        $this->id = Data::string($data, 'id');
        $this->name = Data::string($data, 'name');
        $this->slug = Data::string($data, 'slug');
        $this->subdomain = Data::nullableString($data, 'subdomain');
        $this->domain = Data::nullableString($data, 'domain');
        $this->status = Data::string($data, 'status');
        $this->yourRole = Data::string($data, 'your_role');
        $this->isOwner = Data::bool($data, 'is_owner');
        $this->abilities = Data::strings($data, 'abilities');
        $this->agentsCount = Data::int($data, 'agents_count');
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
