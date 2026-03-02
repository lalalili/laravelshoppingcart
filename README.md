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

Example:

```php
config('lalalili_shopping_cart.decimals');
```

## Breaking changes in v13

- Namespace changed from `Darryldecode\\Cart\\*` to `Lalalili\\ShoppingCart\\*`.
- Facade changed from `CartFacade` to `ShoppingCartFacade`.
- Service provider changed from `CartServiceProvider` to `ShoppingCartServiceProvider`.
- Container binding key changed from `cart` to `shopping_cart`.
- Config key changed from `shopping_cart` to `lalalili_shopping_cart`.
- Config publish target changed from `shopping_cart.php` to `lalalili_shopping_cart.php`.
- No legacy class aliases are provided.

## Testing

```bash
composer test
```

## Static analysis

```bash
composer analyse
```

## License

MIT
