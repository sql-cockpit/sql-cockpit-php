<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

use SqlCockpit\Internal\Data;

/**
 * The credential that made the call.
 */
final class ConnectedVia extends Model
{
    /**
     * JSON: type. "api_key" or "oauth".
     */
    public readonly string $type;

    /**
     * JSON: name.
     */
    public readonly string $name;

    /**
     * JSON: scopes.
     *
     * @var list<string>
     */
    public readonly array $scopes;

    /**
     * JSON: expires_at.
     */
    public readonly ?string $expiresAt;

    /**
     * @param array<mixed> $data
     */
    protected function __construct(array $data)
    {
        parent::__construct($data);
        $this->type = Data::string($data, 'type');
        $this->name = Data::string($data, 'name');
        $this->scopes = Data::strings($data, 'scopes');
        $this->expiresAt = Data::nullableString($data, 'expires_at');
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
