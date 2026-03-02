<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Adapters;

use Lalalili\ShoppingCart\Contracts\EventDispatcherInterface;

class NullEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(string $eventName, array $payload = [], bool $halt = true): mixed
    {
        return null;
    }
}
