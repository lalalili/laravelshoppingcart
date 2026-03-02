<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * @phpstan-type CartConfig array<string, mixed>
 */
class ShoppingCartServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/config/lalalili_shopping_cart.php' => config_path('lalalili_shopping_cart.php'),
            ], 'config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/lalalili_shopping_cart.php', 'lalalili_shopping_cart');

        $this->app->singleton('shopping_cart', function (Application $app): Cart {
            $storageClass = config('lalalili_shopping_cart.storage');
            $eventsClass = config('lalalili_shopping_cart.events');

            $storage = is_string($storageClass) && $storageClass !== '' ? new $storageClass() : $app->make('session');
            $events = is_string($eventsClass) && $eventsClass !== '' ? new $eventsClass() : $app->make('events');

            /** @var CartConfig $config */
            $config = config('lalalili_shopping_cart', []);

            return new Cart(
                $storage,
                $events,
                'shopping_cart',
                '4yTlTDKu3oJOfzD',
                $config
            );
        });
    }
}
