<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\CartConditionCollection;

class CartConditionService
{
    public function appendAndSort(CartConditionCollection $conditions, CartCondition $condition): CartConditionCollection
    {
        if ($condition->getOrder() === 0) {
            $lastCondition = $conditions->last();

            $condition->setOrder($lastCondition instanceof CartCondition ? $lastCondition->getOrder() + 1 : 1);
        }

        $conditions->put($condition->getName(), $condition);

        /** @var CartConditionCollection $sorted */
        $sorted = $conditions->sortBy(static function (CartCondition $item): int {
            return $item->getOrder();
        });

        return $sorted;
    }

    public function filterByType(CartConditionCollection $conditions, string $type): CartConditionCollection
    {
        /** @var CartConditionCollection $filtered */
        $filtered = $conditions->filter(static function (CartCondition $condition) use ($type): bool {
            return $condition->getType() === $type;
        });

        return $filtered;
    }
}
