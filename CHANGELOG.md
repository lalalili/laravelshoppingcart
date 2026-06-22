# Changelog

All notable changes to `lalalili/laravelshoppingcart` are documented in this file.

This package is a Lalalili fork of Crinsane/LaravelShoppingcart (namespace `Lalalili\ShoppingCart`).

## [14.0.2] - 2026-06-21

### Fixed

- `CartFactory` no longer leaks control keys (events/storage objects) into the cart's persisted config, which previously caused `Serialization of Closure` errors with `DBStorage`.

## [14.0.1] - 2026-06-21

### Added

- Named cart instance factory (`config('shopping_cart.instances')`) so a host can register multiple carts (e.g. `shopping_cart` / `cart`, `checkout`, `wishlist`).

## [14.0.0] - 2026-05-11

### Changed

- Allow subclasses to run total pipelines (enables host `Cart` subclasses with custom totals/conditions).

## [13.2.0] - 2026-05-05

### Added

- Laravel 13 dependency-constraint support.

## [13.1.0] - 2026-03-03

### Fixed

- Normalize cart condition key type for PHPStan.

## [13.0.0] - 2026-03-02

### Changed

- v13 release with the Lalalili API (pluggable storage/events/pipelines, `CartPipelineInterface`, `StorageDriverInterface`) and PHP 8.3+ support.

## [12.0.0] - 2025-06-13

### Changed

- Bump dependencies for Laravel 12.
