# lalalili/laravelshoppingcart

Shopping cart package for Laravel 11/12/13 with PHP 8.3+ support.

## Requirements

- PHP `^8.3`
- Laravel `^11.0|^12.0|^13.0`

## Installation

```bash
composer require lalalili/laravelshoppingcart:^14.0
```

Publish config:

```bash
php artisan vendor:publish --provider="Lalalili\\ShoppingCart\\ShoppingCartServiceProvider" --tag="config"
```

This publishes `config/shopping_cart.php`.

## Quick start

```php
use Lalalili\ShoppingCart\Facades\ShoppingCartFacade;

ShoppingCartFacade::session('user-1')->add([
    'id' => 'sku-001',
    'name' => 'Sample Item',
    'price' => 100,
    'quantity' => 2,
    'attributes' => [],
]);

$total = ShoppingCartFacade::session('user-1')->getTotal();
```

## Batch operations

Use batch APIs when copying or rebuilding carts from stored state. They keep the same validation and quantity semantics as `add()`/`remove()`, but persist once per batch.

```php
ShoppingCartFacade::session('user-1')->addMany([
    ['id' => 'sku-001', 'name' => 'Sample Item', 'price' => 100, 'quantity' => 2],
    ['id' => 'sku-002', 'name' => 'Second Item', 'price' => 50, 'quantity' => 1],
]);

ShoppingCartFacade::session('user-1')->removeMany(['sku-001', 'sku-002']);
```

Repeated subtotal and total calls are cached for the lifetime of the cart instance and are invalidated automatically when items, conditions, session keys, or formatting options change.

## Extension points

v14.0 includes hooks for application-specific cart behavior:

```php
return [
    'item_collection_class' => App\Support\CartItem::class,
    'associated_model_resolver' => App\Support\CartModelResolver::class,
    'rounding' => [
        'item_price_before_quantity' => 0,
        'line_subtotal' => ['precision' => 0, 'mode' => 'half_up'],
        'subtotal' => null,
        'total' => null,
    ],
];
```

`item_collection_class` must extend `Lalalili\ShoppingCart\ItemCollection`.
`associated_model_resolver` may be a callable or implement `Lalalili\ShoppingCart\Contracts\AssociatedModelResolverInterface`.
Rounding rules may be `null`, an integer precision, or `['precision' => int, 'mode' => 'half_up|half_down|half_even|half_odd']`.

## Snapshots and total explanations

Use snapshots when a frontend, checkout review page, or regression test needs a stable cart payload.

```php
$cart = ShoppingCartFacade::session('user-1');

$snapshot = $cart->snapshot();
$explain = $cart->explainTotals();
```

`snapshot()` includes items, cart conditions, context, subtotal, total, quantity, version, and hash.
`explainTotals()` includes the item, subtotal, and total condition steps used to produce the final total.
When pipelines have been run, `snapshot()` also includes their `changed`, `warnings`, and `metadata` results.

## Checkout context and stale cart checks

Context stores checkout-specific inputs without changing item rows.

```php
$cart = ShoppingCartFacade::session('user-1')
    ->withContext([
        'customer_id' => 123,
        'channel' => 'web',
        'currency' => 'TWD',
        'coupon_codes' => ['WELCOME100'],
    ]);

$hash = $cart->hash();
$cart->assertHash($hash);
```

`assertHash()` throws `Lalalili\ShoppingCart\Exceptions\StaleCartException` when another tab, request, or device has changed the cart.

## Cart pipelines

Pipelines let applications attach promotion, gift, validation, or checkout transformations without subclassing the cart.

```php
return [
    'pipelines' => [
        'before_totals' => [
            App\Cart\Pipelines\ApplyPromotions::class,
        ],
        'before_checkout' => [
            App\Cart\Pipelines\ValidateInventory::class,
        ],
    ],
];
```

Pipeline classes implement `Lalalili\ShoppingCart\Contracts\CartPipelineInterface`.
`before_totals` runs automatically before subtotal and total calculations. Other stages may be triggered with `runPipelines('before_checkout')`.

## Optional Store API

The package can expose a small JSON cart API, disabled by default:

```php
return [
    'api' => [
        'enabled' => true,
        'require_hash' => false,
        'prefix' => 'cart',
        'middleware' => ['web'],
    ],
];
```

Available endpoints include `GET /cart`, `POST /cart/items`, `POST /cart/items/batch`, `PATCH /cart/items/{id}`, `DELETE /cart/items/{id}`, `DELETE /cart/items`, `POST /cart/conditions`, `PUT /cart/context`, and `DELETE /cart`.
Mutating endpoints validate their payloads and accept an optional cart hash precondition through `If-Match`, `X-Cart-Hash`, `cart_hash`, or `hash`. A stale hash returns HTTP `409`; when `api.require_hash` is enabled, missing hashes return HTTP `428`.

## Configuration key

v14 uses `shopping_cart` config key.

```php
config('shopping_cart.decimals');
```

## v14.x compatibility promise

- `v14.x` is **non-breaking only**.
- Existing public API names stay stable in v14:
  - Facade: `ShoppingCartFacade`
  - Service provider: `ShoppingCartServiceProvider`
  - Container key: `shopping_cart`
  - Config key: `shopping_cart`
- Additive APIs such as `addMany()`, `removeMany()`, `snapshot()`, and `withContext()` may be introduced in v14 minor releases.
- Breaking changes are deferred to `v15`.

## Breaking changes in v14.0.0

- Config key changed from `lalalili_shopping_cart` to `shopping_cart`.
- Config publish target changed from `lalalili_shopping_cart.php` to `shopping_cart.php`.
- Environment variables changed from `LALALILI_SHOPPING_*` and `SHOPPING_*` to `SHOPPING_CART_*`.
- The old `lalalili_shopping_cart` config key is no longer read.

## Testing and quality

```bash
composer test
composer test:pest
composer analyse
```

## Benchmark

```bash
composer bench
```

Latest local benchmark baseline (`v14.x`, PHP 8.4, synthetic data):

| Items | Add (ms) | Update (ms) | getTotal x10 (ms) | Peak memory (MB) |
| --- | ---: | ---: | ---: | ---: |
| 100 | 19.37 | 0.41 | 0.46 | 6.00 |
| 1,000 | 90.95 | 3.66 | 2.12 | 8.00 |
| 10,000 | 900.01 | 37.14 | 23.77 | 14.00 |

## Release flow

Release checklist is available at `.github/ISSUE_TEMPLATE/release-checklist.md`.

Standard flow:

`milestone close -> tag -> release -> packagist ping -> smoke check`

## License

MIT
