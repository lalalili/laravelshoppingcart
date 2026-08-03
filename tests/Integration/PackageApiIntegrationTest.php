<?php

declare (strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageApiIntegrationTestPestCase extends TestCase
{
    /**
     * @return array<int, class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [ShoppingCartServiceProvider::class];
    }
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('shopping_cart.api.enabled', true);
        $app['config']->set('shopping_cart.api.prefix', 'store/cart');
    }
}

uses(PackageApiIntegrationTestPestCase::class);

test('optional api can add and return cart snapshot', function (): void {
    $response = $this->postJson('/store/cart/items', ['id' => 'sku-1', 'name' => 'Sample Item', 'price' => 100, 'quantity' => 2]);
    $response->assertOk()->assertJsonPath('items.0.id', 'sku-1')->assertJsonPath('total', 200)->assertJsonStructure(['hash', 'items', 'conditions', 'context', 'pipelines']);
});

test('optional api rejects invalid item payload', function (): void {
    $this->postJson('/store/cart/items', ['id' => 'sku-1', 'price' => 100, 'quantity' => 2])->assertUnprocessable();
});

test('optional api rejects stale cart hash', function (): void {
    $created = $this->postJson('/store/cart/items', ['id' => 'sku-1', 'name' => 'Sample Item', 'price' => 100, 'quantity' => 2])->assertOk();
    $hash = (string) $created->json('hash');
    $this->putJson('/store/cart/context', ['channel' => 'web', 'cart_hash' => $hash])->assertOk();
    $this->patchJson('/store/cart/items/sku-1', ['quantity' => 1, 'cart_hash' => $hash])->assertStatus(409)->assertJsonStructure(['message', 'current_hash']);
});

test('optional api can require cart hash for mutations', function (): void {
    config()->set('shopping_cart.api.require_hash', true);
    $this->postJson('/store/cart/items', ['id' => 'sku-1', 'name' => 'Sample Item', 'price' => 100, 'quantity' => 2])->assertStatus(428)->assertJsonStructure(['message', 'current_hash']);
});
