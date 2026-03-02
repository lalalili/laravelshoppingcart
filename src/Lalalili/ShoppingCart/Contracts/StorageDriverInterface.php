<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Contracts;

interface StorageDriverInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value): void;
}
