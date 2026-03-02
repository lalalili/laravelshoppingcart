<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Adapters;

use ArgumentCountError;
use Lalalili\ShoppingCart\Contracts\EventDispatcherInterface;
use Throwable;

class LaravelEventDispatcherAdapter implements EventDispatcherInterface
{
    public function __construct(private readonly object $dispatcher)
    {
    }

    public function dispatch(string $eventName, array $payload = [], bool $halt = true): mixed
    {
        if (!method_exists($this->dispatcher, 'dispatch')) {
            return null;
        }

        try {
            return $this->dispatcher->dispatch($eventName, $payload, $halt);
        } catch (ArgumentCountError) {
            return $this->dispatcher->dispatch($eventName, $payload);
        } catch (Throwable) {
            return null;
        }
    }
}
