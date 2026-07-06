# Changelog

All notable changes to `lalalili/laravelshoppingcart` are documented in this file.

This package is a Lalalili fork of Crinsane/LaravelShoppingcart (namespace `Lalalili\ShoppingCart`).

## [14.4.0] - 2026-07-06

### Added

- `Cart::batch(callable)`:批次寫入合併。callback 內所有 storage 寫入先落記憶體緩衝,
  結束時每個 key 只寫一次(N 次 remove/add 的整包序列化 → 1 次)。例外時已執行的
  變更仍落盤(與非批次逐筆寫入語意一致)。新增 `Adapters\BufferedStorageDriver`。

## [14.3.0] - 2026-07-06

### Added

- `rounding.per_condition_step`(預設 `false`):啟用後 subtotal/total 每一條 condition 套用完
  立即以對應層級 rounding rule 收斂(整數幣別建議開啟),取代 host 以子類覆寫
  `getSubTotal()/getTotal()` 實作逐步收斂的需求;host 子類覆寫可移除,同時取回
  base `Cart` 的 totalsCache 與 pipeline 去重效益。

## [14.2.0] - 2026-07-06

### Added

- `Helpers::roundValue()` 的 rounding rule `mode` 新增 `'floor'` / `'ceil'`(依 `precision` 位數無條件捨去/進位),
  供 host 以 config 宣告單件折後價 floor 收斂等政策。
- `Cart::getSubTotalAsInt()` / `Cart::getTotalAsInt()`:以 `(int) round` 收斂未格式化金額,
  供整數幣別(TWD)host 取代不安全的 `(int)` 截斷式轉型。

## [14.1.1] - 2026-07-06

### Fixed

- 修正 `format_numbers=true` 且千分位分隔符啟用時,`getSubTotalWithoutConditions()` 與 cart snapshot 的
  `subtotal_without_conditions` 金額被截斷的問題(`(float) "2,000"` → `2.0`)。`ItemCollection::getPriceSum()`
  新增選用參數 `?bool $formatted = null`(預設維持原 config 行為,向後相容),內部加總改以未格式化數值計算。

## [14.1.0] - 2026-07-05

### Changed

- 移除受安全公告封鎖的 Laravel 11 支援，將相依基線對齊為 `illuminate/* ^12.0|^13.0`（PHP `^8.3`）。仍在 Laravel 11 的 host 需先升級框架後方可更新本套件；執行期行為不變。
- 對齊 CI 測試矩陣，移除 Laravel 11 分支。

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
