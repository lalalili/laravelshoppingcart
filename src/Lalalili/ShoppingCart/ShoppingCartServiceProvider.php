<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * @phpstan-type CartConfig array<string, mixed>
 * @phpstan-type CartInstanceConfig array{binding?: string, instance_name?: string, session_key?: string, cart_class?: class-string<Cart>, storage?: class-string|null, events?: class-string|null}
 */
class ShoppingCartServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerCartInstances();

        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/config/shopping_cart.php' => config_path('shopping_cart.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/config/shopping_cart.php' => config_path('shopping_cart.php'),
            ], 'shopping-cart-config');
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

        $this->app->singleton(CartFactory::class, fn (): CartFactory => new CartFactory($this->app));
    }

    private function registerCartInstances(): void
    {
        /** @var CartConfig $config */
        $config = config('shopping_cart', []);

        foreach ($this->instanceConfigs($config) as $binding => $instanceConfig) {
            if ($this->app->bound($binding)) {
                continue;
            }

            $this->app->singleton($binding, fn (): Cart => $this->app
                ->make(CartFactory::class)
                ->make($instanceConfig));
        }
    }

    /**
     * @param CartConfig $config
     * @return array<string, CartConfig>
     */
    private function instanceConfigs(array $config): array
    {
        $instances = $config['instances'] ?? [];

        if (! is_array($instances) || $instances === []) {
            return [
                'shopping_cart' => array_replace($config, [
                    'binding' => 'shopping_cart',
                    'instance_name' => 'shopping_cart',
                    'session_key' => '4yTlTDKu3oJOfzD',
                ]),
            ];
        }

        $resolved = [];

        foreach ($instances as $key => $instanceConfig) {
            if (! is_array($instanceConfig)) {
                continue;
            }

            /** @var CartInstanceConfig $instanceConfig */
            $binding = $instanceConfig['binding'] ?? $key;

            if (! is_string($binding) || $binding === '') {
                continue;
            }

            $resolved[$binding] = array_replace(
                $config,
                $instanceConfig,
                [
                    'binding' => $binding,
                    'instance_name' => $instanceConfig['instance_name'] ?? $binding,
                    'session_key' => $instanceConfig['session_key'] ?? '4yTlTDKu3oJOfzD',
                ],
            );
        }

        return $resolved === [] ? [
            'shopping_cart' => array_replace($config, [
                'binding' => 'shopping_cart',
                'instance_name' => 'shopping_cart',
                'session_key' => '4yTlTDKu3oJOfzD',
            ]),
        ] : $resolved;
    }
}
