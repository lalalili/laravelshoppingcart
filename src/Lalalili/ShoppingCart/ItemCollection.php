<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Lalalili\ShoppingCart\Contracts\AssociatedModelResolverInterface;
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

        $resolved = $this->resolveAssociatedModel($associatedModel, $id);
        if ($resolved !== self::class) {
            return $resolved;
        }

        if (!is_string($associatedModel) || !class_exists($associatedModel)) {
            return null;
        }

        $model = new $associatedModel();

        if (!method_exists($model, 'find')) {
            return null;
        }

        return $model->find($id);
    }

    private function resolveAssociatedModel(mixed $associatedModel, mixed $id): mixed
    {
        $resolver = $this->config['associated_model_resolver'] ?? null;

        if ($resolver === null || $resolver === '') {
            return self::class;
        }

        if (is_string($resolver) && class_exists($resolver)) {
            $resolver = function_exists('app') ? app($resolver) : new $resolver();
        }

        if ($resolver instanceof AssociatedModelResolverInterface) {
            return $resolver->resolve($this, $associatedModel, $id);
        }

        if (is_callable($resolver)) {
            return $resolver($this, $associatedModel, $id);
        }

        return self::class;
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

            $newPrice = Helpers::roundValue(
                $newPrice,
                Helpers::roundingRule($this->config, 'item_price')
            );

            return Helpers::formatValue($newPrice, (bool) $formatted, $this->config);
        }

        $originalPrice = Helpers::roundValue(
            $originalPrice,
            Helpers::roundingRule($this->config, 'item_price')
        );

        return Helpers::formatValue($originalPrice, (bool) $formatted, $this->config);
    }

    public function getPriceSumWithConditions(bool $formatted = true): float|int|string
    {
        $quantity = Helpers::toFloat($this->get('quantity'));
        $unitPrice = Helpers::roundValue(
            (float) $this->getPriceWithConditions(false),
            Helpers::roundingRule($this->config, 'item_price_before_quantity')
        );
        $lineSubtotal = Helpers::roundValue(
            $unitPrice * $quantity,
            Helpers::roundingRule($this->config, 'line_subtotal')
        );

        return Helpers::formatValue(
            $lineSubtotal,
            (bool) $formatted,
            $this->config
        );
    }
}
