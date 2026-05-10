<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
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
                __DIR__ . '/config/shopping_cart.php' => config_path('shopping_cart.php'),
            ], 'config');
        }

        if ((bool) config('shopping_cart.api.enabled', false)) {
            Route::prefix((string) config('shopping_cart.api.prefix', 'cart'))
                ->middleware(config('shopping_cart.api.middleware', []))
                ->group(__DIR__ . '/../../../routes/api.php');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/shopping_cart.php', 'shopping_cart');

        $this->app->singleton('shopping_cart', function (Application $app): Cart {
            $storageClass = config('shopping_cart.storage');
            $eventsClass = config('shopping_cart.events');

            $storage = is_string($storageClass) && $storageClass !== ''
                ? new $storageClass()
                : ($app->bound('session.store') ? $app->make('session.store') : $app->make('session'));
            $events = is_string($eventsClass) && $eventsClass !== '' ? new $eventsClass() : $app->make('events');

            /** @var CartConfig $config */
            $config = config('shopping_cart', []);

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
