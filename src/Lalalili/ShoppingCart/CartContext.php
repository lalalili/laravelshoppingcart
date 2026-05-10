<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final class CartContext implements Arrayable, JsonSerializable
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private readonly array $data = [])
    {
    }

    /**
     * @param array<string, mixed>|self|null $context
     */
    public static function from(array|self|null $context): self
    {
        if ($context instanceof self) {
            return $context;
        }

        return new self(is_array($context) ? $context : []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function with(string $key, mixed $value): self
    {
        $data = $this->data;
        $data[$key] = $value;

        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
