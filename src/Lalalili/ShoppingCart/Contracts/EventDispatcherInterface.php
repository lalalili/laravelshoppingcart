<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Contracts;

interface EventDispatcherInterface
{
    /**
     * @param array<int, mixed> $payload
     */
    public function dispatch(string $eventName, array $payload = [], bool $halt = true): mixed;
}
