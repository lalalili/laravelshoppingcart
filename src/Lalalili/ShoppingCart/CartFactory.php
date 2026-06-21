<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Foundation\Application;

/**
 * @phpstan-type CartConfig array<string, mixed>
 */
class CartFactory
{
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * @param CartConfig $config
     */
    public function make(array $config): Cart
    {
        $cartClass = $this->resolveCartClass($config['cart_class'] ?? Cart::class);

        return new $cartClass(
            $this->resolveStorage($config['storage'] ?? null, $config['storage_parameters'] ?? []),
            $this->resolveEvents($config['events'] ?? null, $config['events_parameters'] ?? []),
            $this->resolveString($config['instance_name'] ?? null, 'shopping_cart'),
            $this->resolveString($config['session_key'] ?? null, '4yTlTDKu3oJOfzD'),
            $config,
        );
    }

    private function resolveStorage(mixed $storageClass, mixed $parameters = []): mixed
    {
        if (is_object($storageClass)) {
            return $storageClass;
        }

        if (is_string($storageClass) && $storageClass !== '') {
            return $this->app->make($storageClass, is_array($parameters) ? $parameters : []);
        }

        if ($this->app->bound('session.store')) {
            return $this->app->make('session.store');
        }

        return $this->app->make('session');
    }

    private function resolveEvents(mixed $eventsClass, mixed $parameters = []): mixed
    {
        if (is_object($eventsClass)) {
            return $eventsClass;
        }

        if (is_string($eventsClass) && $eventsClass !== '') {
            return $this->app->make($eventsClass, is_array($parameters) ? $parameters : []);
        }

        return $this->app->make('events');
    }

    /**
     * @return class-string<Cart>
     */
    private function resolveCartClass(mixed $cartClass): string
    {
        if (is_string($cartClass) && is_a($cartClass, Cart::class, true)) {
            return $cartClass;
        }

        return Cart::class;
    }

    private function resolveString(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
