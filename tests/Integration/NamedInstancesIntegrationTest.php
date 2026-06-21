<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;

class NamedInstancesIntegrationTest extends TestCase
{
    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ShoppingCartServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('shopping_cart.instances', [
            'cart' => [
                'binding' => 'cart',
                'instance_name' => 'cart',
                'session_key' => 'cart-session-key',
            ],
            'checkout' => [
                'binding' => 'checkout',
                'instance_name' => 'checkout',
                'session_key' => 'checkout-session-key',
            ],
        ]);
    }

    public function test_provider_registers_configured_named_instances(): void
    {
        $this->assertTrue($this->app->bound('cart'));
        $this->assertTrue($this->app->bound('checkout'));

        $cart = $this->app->make('cart');
        $checkout = $this->app->make('checkout');

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertInstanceOf(Cart::class, $checkout);
        $this->assertSame('cart', $cart->getInstanceName());
        $this->assertSame('checkout', $checkout->getInstanceName());
    }
}
