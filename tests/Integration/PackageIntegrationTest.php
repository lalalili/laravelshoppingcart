<?php

declare (strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\Facades\ShoppingCartFacade;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageIntegrationTestPestCase extends TestCase
{
    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [ShoppingCartServiceProvider::class];
    }
}

uses(PackageIntegrationTestPestCase::class);

test('provider registers shopping cart binding', function (): void {
    $this->assertTrue($this->app->bound('shopping_cart'));
    $this->assertInstanceOf(Cart::class, $this->app->make('shopping_cart'));
});

test('provider exposes config publish path', function (): void {
    $paths = ServiceProvider::pathsToPublish(ShoppingCartServiceProvider::class, 'config');
    $this->assertNotEmpty($paths);
    $from = array_key_first($paths);
    $to = $from !== null ? $paths[$from] : null;
    $this->assertStringContainsString('shopping_cart.php', (string) $from);
    $this->assertStringContainsString('shopping_cart.php', (string) $to);
});

test('provider exposes named config publish path', function (): void {
    $paths = ServiceProvider::pathsToPublish(ShoppingCartServiceProvider::class, 'shopping-cart-config');
    $this->assertNotEmpty($paths);
    $from = array_key_first($paths);
    $to = $from !== null ? $paths[$from] : null;
    $this->assertStringContainsString('shopping_cart.php', (string) $from);
    $this->assertStringContainsString('shopping_cart.php', (string) $to);
});

test('facade resolves cart instance with session switching', function (): void {
    $cart = ShoppingCartFacade::session('integration-user');
    $this->assertInstanceOf(Cart::class, $cart);
    $this->assertSame('shopping_cart', $cart->getInstanceName());
});

test('cart fallbacks when storage or dispatcher are invalid', function (): void {
    $cart = new Cart(new class () {
    }, new class () {
    }, 'invalid-boundaries', 'invalid-key', []);
    $this->assertTrue($cart->isEmpty());
    $this->assertTrue($cart->clear());
});
