<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Adapters;

use ArgumentCountError;
use Lalalili\ShoppingCart\Contracts\StorageDriverInterface;

class SessionStorageDriver implements StorageDriverInterface
{
    public function __construct(private readonly object $session)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!method_exists($this->session, 'get')) {
            return $default;
        }

        try {
            return $this->session->get($key, $default);
        } catch (ArgumentCountError) {
            $value = $this->session->get($key);

            return $value ?? $default;
        }
    }

    public function put(string $key, mixed $value): void
    {
        if (!method_exists($this->session, 'put')) {
            return;
        }

        $this->session->put($key, $value);
    }
}
