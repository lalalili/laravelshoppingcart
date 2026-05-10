<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Integration;

use Illuminate\Support\ServiceProvider;
use Lalalili\ShoppingCart\ShoppingCartServiceProvider;
use Orchestra\Testbench\TestCase;

class PackageApiIntegrationTest extends TestCase
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
        $app['config']->set('shopping_cart.api.enabled', true);
        $app['config']->set('shopping_cart.api.prefix', 'store/cart');
    }

    public function test_optional_api_can_add_and_return_cart_snapshot(): void
    {
        $response = $this->postJson('/store/cart/items', [
            'id' => 'sku-1',
            'name' => 'Sample Item',
            'price' => 100,
            'quantity' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('items.0.id', 'sku-1')
            ->assertJsonPath('total', 200)
            ->assertJsonStructure(['hash', 'items', 'conditions', 'context', 'pipelines']);
    }

    public function test_optional_api_rejects_invalid_item_payload(): void
    {
        $this->postJson('/store/cart/items', [
            'id' => 'sku-1',
            'price' => 100,
            'quantity' => 2,
        ])->assertUnprocessable();
    }

    public function test_optional_api_rejects_stale_cart_hash(): void
    {
        $created = $this->postJson('/store/cart/items', [
            'id' => 'sku-1',
            'name' => 'Sample Item',
            'price' => 100,
            'quantity' => 2,
        ])->assertOk();

        $hash = (string) $created->json('hash');

        $this->putJson('/store/cart/context', [
            'channel' => 'web',
            'cart_hash' => $hash,
        ])->assertOk();

        $this->patchJson('/store/cart/items/sku-1', [
            'quantity' => 1,
            'cart_hash' => $hash,
        ])
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'current_hash']);
    }

    public function test_optional_api_can_require_cart_hash_for_mutations(): void
    {
        config()->set('shopping_cart.api.require_hash', true);

        $this->postJson('/store/cart/items', [
            'id' => 'sku-1',
            'name' => 'Sample Item',
            'price' => 100,
            'quantity' => 2,
        ])
            ->assertStatus(428)
            ->assertJsonStructure(['message', 'current_hash']);
    }
}
