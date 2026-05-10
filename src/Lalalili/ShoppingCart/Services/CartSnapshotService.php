<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Services;

use Illuminate\Support\Collection;
use Lalalili\ShoppingCart\CartCollection;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\CartConditionCollection;
use Lalalili\ShoppingCart\CartContext;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\ItemAttributeCollection;
use Lalalili\ShoppingCart\ItemCollection;

/**
 * @phpstan-type CartConfig array<string, mixed>
 */
class CartSnapshotService
{
    /**
     * @param CartConfig $config
     * @return array<string, mixed>
     */
    public function snapshot(
        CartCollection $content,
        CartConditionCollection $conditions,
        CartContext $context,
        int|string|null $version,
        string $instanceName,
        string $sessionKey,
        bool $formatted,
        array $config,
        string $hash
    ): array {
        $totalsService = new CartTotalsService();
        $subtotal = (float) $totalsService->subTotal($content, $conditions, false, $config);

        return [
            'instance' => $instanceName,
            'session_key' => $sessionKey,
            'version' => $version,
            'hash' => $hash,
            'context' => $context->toArray(),
            'items' => $this->items($content, $formatted),
            'conditions' => $this->conditions($conditions),
            'subtotal_without_conditions' => $totalsService->subTotalWithoutConditions($content, $formatted, $config),
            'subtotal' => $this->format($subtotal, $formatted, $config),
            'total' => $totalsService->total($conditions, $subtotal, $formatted, $config),
            'quantity' => $totalsService->totalQuantity($content),
        ];
    }

    /**
     * @param CartConfig $config
     * @return array<string, mixed>
     */
    public function explainTotals(
        CartCollection $content,
        CartConditionCollection $conditions,
        CartContext $context,
        bool $formatted,
        array $config
    ): array {
        $lineSubtotals = [];
        $subtotalWithoutConditions = 0.0;

        foreach ($content as $item) {
            $itemExplanation = $this->explainItem($item, $formatted, $config);
            $subtotalWithoutConditions += (float) $item->getPriceSum();
            $lineSubtotals[] = $itemExplanation;
        }

        $subtotalBase = array_sum(array_map(
            static fn (array $item): float => (float) $item['line_subtotal_raw'],
            $lineSubtotals
        ));

        $subtotalSteps = $this->applyConditions(
            $subtotalBase,
            $conditions->filter(static fn (CartCondition $condition): bool => $condition->getTarget() === 'subtotal'),
            $formatted,
            $config
        );
        $subtotal = $subtotalSteps === [] ? $subtotalBase : (float) end($subtotalSteps)['after_raw'];
        $subtotal = Helpers::roundValue($subtotal, Helpers::roundingRule($config, 'subtotal'));

        $totalSteps = $this->applyConditions(
            $subtotal,
            $conditions->filter(static fn (CartCondition $condition): bool => $condition->getTarget() === 'total'),
            $formatted,
            $config
        );
        $total = $totalSteps === [] ? $subtotal : (float) end($totalSteps)['after_raw'];
        $total = Helpers::roundValue($total, Helpers::roundingRule($config, 'total'));

        return [
            'context' => $context->toArray(),
            'items' => array_map(static function (array $item): array {
                unset($item['line_subtotal_raw']);

                return $item;
            }, $lineSubtotals),
            'subtotal_without_conditions' => $this->format($subtotalWithoutConditions, $formatted, $config),
            'subtotal_base' => $this->format($subtotalBase, $formatted, $config),
            'subtotal_conditions' => $subtotalSteps,
            'subtotal' => $this->format($subtotal, $formatted, $config),
            'total_conditions' => $totalSteps,
            'total' => $this->format($total, $formatted, $config),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hashPayload(
        CartCollection $content,
        CartConditionCollection $conditions,
        CartContext $context,
        int|string|null $version
    ): array {
        return [
            'version' => $version,
            'context' => $context->toArray(),
            'items' => $this->items($content, false),
            'conditions' => $this->conditions($conditions),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(CartCollection $content, bool $formatted): array
    {
        $items = [];

        foreach ($content as $item) {
            $attributes = $item->get('attributes', []);
            if ($attributes instanceof ItemAttributeCollection || $attributes instanceof Collection) {
                $attributes = $attributes->toArray();
            }

            $items[] = [
                'id' => $item->get('id'),
                'name' => $item->get('name'),
                'price' => $formatted ? $item->getPriceWithConditions(true) : $item->get('price'),
                'quantity' => $item->get('quantity'),
                'attributes' => is_array($attributes) ? $attributes : [],
                'conditions' => $this->itemConditions($item),
                'associatedModel' => $item->get('associatedModel'),
                'price_with_conditions' => $item->getPriceWithConditions($formatted),
                'line_subtotal' => $item->getPriceSumWithConditions($formatted),
            ];
        }

        return $items;
    }

    /**
     * @param CartConfig $config
     * @return array<string, mixed>
     */
    private function explainItem(ItemCollection $item, bool $formatted, array $config): array
    {
        $price = Helpers::toFloat($item->get('price'));
        $steps = [];
        $current = $price;

        foreach ($this->itemConditionObjects($item) as $condition) {
            $before = $current;
            $current = $condition->applyCondition($current);
            $steps[] = $this->conditionStep($condition, $before, $current, $formatted, $config);
        }

        $unitPrice = Helpers::roundValue($current, Helpers::roundingRule($config, 'item_price_before_quantity'));
        $lineSubtotal = Helpers::roundValue(
            $unitPrice * Helpers::toFloat($item->get('quantity')),
            Helpers::roundingRule($config, 'line_subtotal')
        );

        return [
            'id' => $item->get('id'),
            'name' => $item->get('name'),
            'price' => $this->format($price, $formatted, $config),
            'quantity' => $item->get('quantity'),
            'condition_steps' => $steps,
            'price_with_conditions' => $this->format($current, $formatted, $config),
            'line_subtotal' => $this->format($lineSubtotal, $formatted, $config),
            'line_subtotal_raw' => $lineSubtotal,
        ];
    }

    /**
     * @param iterable<int|string, CartCondition> $conditions
     * @param CartConfig $config
     * @return list<array<string, mixed>>
     */
    private function applyConditions(float $base, iterable $conditions, bool $formatted, array $config): array
    {
        $steps = [];
        $current = $base;

        foreach ($conditions as $condition) {
            $before = $current;
            $current = $condition->applyCondition($current);
            $steps[] = $this->conditionStep($condition, $before, $current, $formatted, $config);
        }

        return $steps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conditions(CartConditionCollection $conditions): array
    {
        $normalized = [];

        foreach ($conditions as $condition) {
            $normalized[] = $this->condition($condition);
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itemConditions(ItemCollection $item): array
    {
        return array_map(
            fn (CartCondition $condition): array => $this->condition($condition),
            $this->itemConditionObjects($item)
        );
    }

    /**
     * @return list<CartCondition>
     */
    private function itemConditionObjects(ItemCollection $item): array
    {
        $conditions = $item->getConditions();

        if ($conditions instanceof CartCondition) {
            return [$conditions];
        }

        return array_values($conditions);
    }

    /**
     * @return array<string, mixed>
     */
    private function condition(CartCondition $condition): array
    {
        return [
            'name' => $condition->getName(),
            'type' => $condition->getType(),
            'value' => $condition->getValue(),
            'target' => $condition->getTarget(),
            'order' => $condition->getOrder(),
            'attributes' => $condition->getAttributes(),
        ];
    }

    /**
     * @param CartConfig $config
     * @return array<string, mixed>
     */
    private function conditionStep(
        CartCondition $condition,
        float $before,
        float $after,
        bool $formatted,
        array $config
    ): array {
        $calculatedValue = $condition->getCalculatedValue($before);

        return [
            'condition' => $this->condition($condition),
            'before' => $this->format($before, $formatted, $config),
            'before_raw' => $before,
            'calculated_value' => $this->format($calculatedValue, $formatted, $config),
            'calculated_value_raw' => $calculatedValue,
            'after' => $this->format($after, $formatted, $config),
            'after_raw' => $after,
        ];
    }

    /**
     * @param CartConfig $config
     */
    private function format(float $value, bool $formatted, array $config): float|int|string
    {
        return Helpers::formatValue($value, $formatted, $config);
    }
}
