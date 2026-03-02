<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\Contracts\EventDispatcherInterface;

class CartEventBridge
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly string $instanceName,
        private readonly Cart $cart
    ) {
    }

    public function dispatch(string $eventName, mixed $value = []): mixed
    {
        return $this->dispatcher->dispatch(
            $this->instanceName . '.' . $eventName,
            [$value, $this->cart],
            true
        );
    }
}
