<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Lalalili\ShoppingCart\Helpers\Helpers;

/**
 * @extends Collection<string, mixed>
 */
class ItemCollection extends Collection
{
    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * @param Arrayable<string, mixed>|iterable<string, mixed>|null $items
     * @param array<string, mixed> $config
     */
    public function __construct(Arrayable|iterable|null $items, array $config = [])
    {
        parent::__construct($items ?? []);

        $this->config = $config;
    }

    public function getPriceSum(): float|int|string
    {
        $price = Helpers::toFloat($this->get('price'));
        $quantity = Helpers::toFloat($this->get('quantity'));

        return Helpers::formatValue(
            $price * $quantity,
            (bool) ($this->config['format_numbers'] ?? false),
            $this->config
        );
    }

    public function __get($name): mixed
    {
        if ($this->has($name) || $name === 'model') {
            return $this->get($name) ?? $this->getAssociatedModel();
        }

        return null;
    }

    protected function getAssociatedModel(): mixed
    {
        if (!$this->has('associatedModel')) {
            return null;
        }

        $associatedModel = $this->get('associatedModel');
        $id = $this->get('id');

        if (!is_string($associatedModel) || !class_exists($associatedModel)) {
            return null;
        }

        $model = new $associatedModel();

        if (!method_exists($model, 'find')) {
            return null;
        }

        return $model->find($id);
    }

    public function hasConditions(): bool
    {
        if (!$this->has('conditions')) {
            return false;
        }

        $conditions = $this->get('conditions');

        if (is_array($conditions)) {
            return count($conditions) > 0;
        }

        return $conditions instanceof CartCondition;
    }

    /**
     * @return CartCondition|array<int, CartCondition>
     */
    public function getConditions(): CartCondition|array
    {
        if (!$this->hasConditions()) {
            return [];
        }

        $conditions = $this->get('conditions');

        if ($conditions instanceof CartCondition) {
            return $conditions;
        }

        return array_values(array_filter(
            is_array($conditions) ? $conditions : [],
            static fn (mixed $condition): bool => $condition instanceof CartCondition
        ));
    }

    public function getPriceWithConditions(bool $formatted = true): float|int|string
    {
        $originalPrice = Helpers::toFloat($this->get('price'));
        $newPrice = 0.00;
        $processed = 0;

        if ($this->hasConditions()) {
            $conditions = $this->getConditions();

            if (is_array($conditions)) {
                foreach ($conditions as $condition) {
                    $toBeCalculated = $processed > 0 ? $newPrice : $originalPrice;
                    $newPrice = $condition->applyCondition($toBeCalculated);
                    $processed++;
                }
            } else {
                $newPrice = $conditions->applyCondition($originalPrice);
            }

            return Helpers::formatValue($newPrice, (bool) $formatted, $this->config);
        }

        return Helpers::formatValue($originalPrice, (bool) $formatted, $this->config);
    }

    public function getPriceSumWithConditions(bool $formatted = true): float|int|string
    {
        $quantity = Helpers::toFloat($this->get('quantity'));

        return Helpers::formatValue(
            (float) $this->getPriceWithConditions(false) * $quantity,
            (bool) $formatted,
            $this->config
        );
    }
}
