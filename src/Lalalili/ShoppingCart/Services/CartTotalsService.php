<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Lalalili\ShoppingCart\CartCollection;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\CartConditionCollection;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\ItemCollection;

/**
 * @phpstan-type CartConfig array{format_numbers: bool, decimals: int, dec_point: string, thousands_sep: string}
 */
class CartTotalsService
{
    /**
     * @param CartConfig $config
     */
    public function subTotalWithoutConditions(CartCollection $cart, bool $formatted, array $config): float|int|string
    {
        $sum = $cart->sum(static function (ItemCollection $item): float {
            return (float) $item->getPriceSum();
        });

        return Helpers::formatValue((float) $sum, $formatted, $config);
    }

    /**
     * @param CartConfig $config
     */
    public function subTotal(
        CartCollection $cart,
        CartConditionCollection $conditions,
        bool $formatted,
        array $config
    ): float|int|string {
        $sum = $cart->sum(static function (ItemCollection $item): float {
            return (float) $item->getPriceSumWithConditions(false);
        });

        $subtotalConditions = $conditions->filter(static function (CartCondition $condition): bool {
            return $condition->getTarget() === 'subtotal';
        });

        if ($subtotalConditions->isEmpty()) {
            return Helpers::formatValue((float) $sum, $formatted, $config);
        }

        $newTotal = 0.00;
        $process = 0;

        $subtotalConditions->each(static function (CartCondition $condition) use ($sum, &$newTotal, &$process): void {
            $toBeCalculated = ($process > 0) ? $newTotal : (float) $sum;
            $newTotal = $condition->applyCondition($toBeCalculated);
            $process++;
        });

        return Helpers::formatValue((float) $newTotal, $formatted, $config);
    }

    /**
     * @param CartConfig $config
     */
    public function total(
        CartConditionCollection $conditions,
        float $subTotal,
        bool $formatted,
        array $config
    ): float|int|string {
        $totalConditions = $conditions->filter(static function (CartCondition $condition): bool {
            return $condition->getTarget() === 'total';
        });

        if ($totalConditions->isEmpty()) {
            return Helpers::formatValue($subTotal, $formatted, $config);
        }

        $newTotal = 0.00;
        $process = 0;

        $totalConditions->each(static function (CartCondition $condition) use ($subTotal, &$newTotal, &$process): void {
            $toBeCalculated = ($process > 0) ? $newTotal : $subTotal;
            $newTotal = $condition->applyCondition($toBeCalculated);
            $process++;
        });

        return Helpers::formatValue($newTotal, $formatted, $config);
    }

    public function totalQuantity(CartCollection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        return (int) $items->sum(static function (ItemCollection $item): int {
            return Helpers::toInt($item->get('quantity', 0));
        });
    }
}
