<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Lalalili\ShoppingCart\CartCollection;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\CartConditionCollection;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\ItemCollection;

/**
 * @phpstan-type CartConfig array<string, mixed>
 */
class CartTotalsService
{
    /**
     * @param CartConfig $config
     */
    public function subTotalWithoutConditions(CartCollection $cart, bool $formatted, array $config): float|int|string
    {
        $sum = $cart->sum(static function (ItemCollection $item): float {
            return (float) $item->getPriceSum(false);
        });

        $sum = Helpers::roundValue((float) $sum, Helpers::roundingRule($config, 'subtotal_without_conditions'));

        return Helpers::formatValue($sum, $formatted, $config);
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
            $sum = Helpers::roundValue((float) $sum, Helpers::roundingRule($config, 'subtotal'));

            return Helpers::formatValue($sum, $formatted, $config);
        }

        $newTotal = 0.00;
        $process = 0;
        $stepRule = self::perStepRule($config, 'subtotal');

        $subtotalConditions->each(static function (CartCondition $condition) use ($sum, $stepRule, &$newTotal, &$process): void {
            $toBeCalculated = ($process > 0) ? $newTotal : (float) $sum;
            $newTotal = Helpers::roundValue((float) $condition->applyCondition($toBeCalculated), $stepRule);
            $process++;
        });

        $newTotal = Helpers::roundValue((float) $newTotal, Helpers::roundingRule($config, 'subtotal'));

        return Helpers::formatValue($newTotal, $formatted, $config);
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
            $subTotal = Helpers::roundValue($subTotal, Helpers::roundingRule($config, 'total'));

            return Helpers::formatValue($subTotal, $formatted, $config);
        }

        $newTotal = 0.00;
        $process = 0;
        $stepRule = self::perStepRule($config, 'total');

        $totalConditions->each(static function (CartCondition $condition) use ($subTotal, $stepRule, &$newTotal, &$process): void {
            $toBeCalculated = ($process > 0) ? $newTotal : $subTotal;
            $newTotal = Helpers::roundValue((float) $condition->applyCondition($toBeCalculated), $stepRule);
            $process++;
        });

        $newTotal = Helpers::roundValue($newTotal, Helpers::roundingRule($config, 'total'));

        return Helpers::formatValue($newTotal, $formatted, $config);
    }

    /**
     * 啟用 `rounding.per_condition_step` 時,每一條 condition 套用後立即以
     * 對應層級(subtotal/total)的 rounding rule 收斂;否則僅在最後收斂一次。
     *
     * @param CartConfig $config
     */
    private static function perStepRule(array $config, string $levelKey): mixed
    {
        if (! (bool) (Helpers::roundingRule($config, 'per_condition_step') ?? false)) {
            return null;
        }

        return Helpers::roundingRule($config, $levelKey);
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
