<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final class CartPipelineResult implements Arrayable, JsonSerializable
{
    /**
     * @param list<string> $warnings
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly bool $changed = false,
        private readonly array $warnings = [],
        private readonly array $metadata = []
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param list<string> $warnings
     * @param array<string, mixed> $metadata
     */
    public static function make(bool $changed = false, array $warnings = [], array $metadata = []): self
    {
        return new self($changed, array_values($warnings), $metadata);
    }

    public function changed(): bool
    {
        return $this->changed;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function merge(self $result): self
    {
        return new self(
            $this->changed || $result->changed(),
            array_values(array_merge($this->warnings, $result->warnings())),
            array_merge($this->metadata, $result->metadata())
        );
    }

    /**
     * @return array{changed: bool, warnings: list<string>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'changed' => $this->changed,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return array{changed: bool, warnings: list<string>, metadata: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
