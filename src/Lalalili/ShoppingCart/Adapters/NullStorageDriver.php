<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Adapters;

use Lalalili\ShoppingCart\Contracts\StorageDriverInterface;

class NullStorageDriver implements StorageDriverInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function put(string $key, mixed $value): void
    {
    }
}
