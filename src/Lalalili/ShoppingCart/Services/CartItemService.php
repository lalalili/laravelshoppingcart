<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\Helpers\Helpers;

/**
 * @phpstan-type ItemData array<string, mixed>
 */
class CartItemService
{
    /**
     * @param ItemData $item
     * @return ItemData
     */
    public function updateQuantityRelative(array $item, string $key, mixed $value): array
    {
        $stringValue = Helpers::toString($value);
        $currentValue = Helpers::toInt($item[$key] ?? 0);

        if (preg_match('/\-/', $stringValue) === 1) {
            $decrementValue = Helpers::toInt(str_replace('-', '', $stringValue));

            if (($currentValue - $decrementValue) > 0) {
                $currentValue -= $decrementValue;
            }
        } elseif (preg_match('/\+/', $stringValue) === 1) {
            $currentValue += Helpers::toInt(str_replace('+', '', $stringValue));
        } else {
            $currentValue += Helpers::toInt($value);
        }

        $item[$key] = $currentValue;

        return $item;
    }

    /**
     * @param ItemData $item
     * @return ItemData
     */
    public function updateQuantityNotRelative(array $item, string $key, mixed $value): array
    {
        $item[$key] = Helpers::toInt($value);

        return $item;
    }

    /**
     * @param ItemData $item
     */
    public function hasConditions(array $item): bool
    {
        if (!isset($item['conditions'])) {
            return false;
        }

        if (is_array($item['conditions'])) {
            return count($item['conditions']) > 0;
        }

        return $item['conditions'] instanceof CartCondition;
    }

    /**
     * @param ItemData $item
     * @return ItemData
     */
    public function removeConditionByName(array $item, string $conditionName): array
    {
        if (!$this->hasConditions($item)) {
            return $item;
        }

        $conditions = $item['conditions'];

        if (is_array($conditions)) {
            $item['conditions'] = array_values(array_filter(
                $conditions,
                static fn (mixed $condition): bool => $condition instanceof CartCondition
                    && $condition->getName() !== $conditionName
            ));

            return $item;
        }

        if ($conditions instanceof CartCondition && $conditions->getName() === $conditionName) {
            $item['conditions'] = [];
        }

        return $item;
    }
}
