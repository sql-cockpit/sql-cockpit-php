<?php

declare(strict_types=1);

namespace SqlCockpit\Model;

/**
 * Base class of every value the SDK returns. Properties are readonly and camelCase;
 * toArray() gives back the data exactly as the API sent it (snake_case keys).
 */
abstract class Model implements \JsonSerializable
{
    /**
     * @param array<mixed> $data
     */
    protected function __construct(private readonly array $data)
    {
    }

    /**
     * The data as the API sent it, with its original snake_case keys.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
