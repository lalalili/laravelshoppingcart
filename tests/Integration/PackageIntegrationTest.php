<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\Facades\ShoppingCartFacade;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageIntegrationTest extends TestCase
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

    public function test_provider_registers_shopping_cart_binding(): void
    {
        $this->assertTrue($this->app->bound('shopping_cart'));
        $this->assertInstanceOf(Cart::class, $this->app->make('shopping_cart'));
    }

    public function test_provider_exposes_config_publish_path(): void
    {
        $paths = ServiceProvider::pathsToPublish(ShoppingCartServiceProvider::class, 'config');

        $this->assertNotEmpty($paths);

        $from = array_key_first($paths);
        $to = $from !== null ? $paths[$from] : null;

        $this->assertStringContainsString('lalalili_shopping_cart.php', (string) $from);
        $this->assertStringContainsString('lalalili_shopping_cart.php', (string) $to);
    }

    public function test_facade_resolves_cart_instance_with_session_switching(): void
    {
        $cart = ShoppingCartFacade::session('integration-user');

        $this->assertInstanceOf(Cart::class, $cart);
        $this->assertSame('shopping_cart', $cart->getInstanceName());
    }

    public function test_cart_fallbacks_when_storage_or_dispatcher_are_invalid(): void
    {
        $cart = new Cart(
            new class {
            },
            new class {
            },
            'invalid-boundaries',
            'invalid-key',
            []
        );

        $this->assertTrue($cart->isEmpty());
        $this->assertTrue($cart->clear());
    }
}
