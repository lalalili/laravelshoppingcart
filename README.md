# lalalili/laravelshoppingcart

Shopping cart package for Laravel 11/12 with PHP 8.3+ support.

## Requirements

- PHP `^8.3`
- Laravel `^11.0|^12.0`

## Installation

```bash
composer require lalalili/laravelshoppingcart:^13.0
```

Publish config:

```bash
php artisan vendor:publish --provider="Lalalili\\ShoppingCart\\ShoppingCartServiceProvider" --tag="config"
```

This publishes `config/lalalili_shopping_cart.php`.

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

## Configuration key

v13 uses `lalalili_shopping_cart` config key.

```php
config('lalalili_shopping_cart.decimals');
```

## v13.x compatibility promise

- `v13.x` is **non-breaking only**.
- Existing public API names stay stable in v13:
  - Facade: `ShoppingCartFacade`
  - Service provider: `ShoppingCartServiceProvider`
  - Container key: `shopping_cart`
  - Config key: `lalalili_shopping_cart`
- Breaking changes are deferred to `v14`.

## Breaking changes in v13.0.0

- Namespace changed from `Darryldecode\\Cart\\*` to `Lalalili\\ShoppingCart\\*`.
- Facade changed from `CartFacade` to `ShoppingCartFacade`.
- Service provider changed from `CartServiceProvider` to `ShoppingCartServiceProvider`.
- Container binding key changed from `cart` to `shopping_cart`.
- Config key changed from `shopping_cart` to `lalalili_shopping_cart`.
- Config publish target changed from `shopping_cart.php` to `lalalili_shopping_cart.php`.
- No legacy class aliases are provided.

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

Latest local benchmark baseline (`v13.x`, PHP 8.4, synthetic data):

| Items | Add (ms) | Update (ms) | getTotal x10 (ms) | Peak memory (MB) |
| --- | ---: | ---: | ---: | ---: |
| 100 | 37.79 | 0.36 | 1.93 | 4.00 |
| 1,000 | 87.80 | 2.98 | 15.57 | 4.00 |
| 10,000 | 870.79 | 29.15 | 164.92 | 12.00 |

## Release flow

Release checklist is available at `.github/ISSUE_TEMPLATE/release-checklist.md`.

Standard flow:

`milestone close -> tag -> release -> packagist ping -> smoke check`

## License

MIT
