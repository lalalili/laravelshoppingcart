<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartFactory;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionProperty;
use stdClass;

class CartFactorySerializationTest extends TestCase
{
    /**
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ShoppingCartServiceProvider::class,
        ];
    }

    public function test_factory_does_not_leak_control_inputs_into_cart_config(): void
    {
        // A closure listener makes the dispatcher non-serializable, mirroring a real app.
        $this->app['events']->listen('cart.factory.serialization', fn () => null);

        $cart = (new CartFactory($this->app))->make([
            'cart_class' => Cart::class,
            'storage' => new stdClass(),
            'events' => $this->app['events'],
            'instance_name' => 'cart',
            'session_key' => 'cart-session-key',
        ]);

        $config = $this->cartConfig($cart);

        $this->assertArrayNotHasKey('cart_class', $config);
        $this->assertArrayNotHasKey('storage', $config);
        $this->assertArrayNotHasKey('events', $config);

        // Regression: persisting a factory-built cart previously threw
        // "Serialization of 'Closure' is not allowed" because the events
        // dispatcher leaked into the cart's persisted config.
        $this->assertIsString(serialize($config));
    }

    /**
     * @return array<string, mixed>
     */
    private function cartConfig(Cart $cart): array
    {
        $property = new ReflectionProperty(Cart::class, 'config');
        $property->setAccessible(true);

        /** @var array<string, mixed> $value */
        $value = $property->getValue($cart);

        return $value;
    }
}
