<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 1/17/2015
 * Time: 11:03 AM
 */

use Lalalili\ShoppingCart\Helpers\Helpers;
use Illuminate\Support\Collection;

class ItemCollection extends Collection
{
    /**
     * Sets the config parameters.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * ItemCollection constructor.
     * @param array|mixed $items
     * @param $config
     */
    public function __construct(mixed $items, array $config = [])
    {
        parent::__construct($items);

        $this->config = $config;
    }

    /**
     * get the sum of price
     *
     * @return float|int|string
     */
    public function getPriceSum()
    {
        $price = (float) $this->get('price');
        $quantity = (float) $this->get('quantity');

        return Helpers::formatValue($price * $quantity, (bool) ($this->config['format_numbers'] ?? false), $this->config);
    }

    public function __get($name)
    {
        if ($this->has($name) || $name == 'model') {
            return !is_null($this->get($name)) ? $this->get($name) : $this->getAssociatedModel();
        }
        return null;
    }

    /**
     * return the associated model of an item
     *
     * @return mixed|null
     */
    protected function getAssociatedModel()
    {
        if (!$this->has('associatedModel')) {
            return null;
        }

        $associatedModel = $this->get('associatedModel');

        return (new $associatedModel())->find($this->get('id'));
    }

    /**
     * check if item has conditions
     *
     * @return bool
     */
    public function hasConditions()
    {
        if (!isset($this['conditions'])) {
            return false;
        }
        if (is_array($this['conditions'])) {
            return count($this['conditions']) > 0;
        }
        $conditionInstance = "Lalalili\\ShoppingCart\\CartCondition";
        if ($this['conditions'] instanceof $conditionInstance) {
            return true;
        }

        return false;
    }

    /**
     * check if item has conditions
     *
     * @return mixed|null
     */
    public function getConditions()
    {
        if (!$this->hasConditions()) {
            return [];
        }
        return $this['conditions'];
    }

    /**
     * get the single price in which conditions are already applied
     * @param bool $formatted
     * @return float|int|string
     */
    public function getPriceWithConditions($formatted = true)
    {
        $originalPrice = (float) $this->get('price');
        $newPrice = 0.00;
        $processed = 0;

        if ($this->hasConditions()) {
            $conditions = $this->get('conditions');

            if (is_array($conditions)) {
                foreach ($conditions as $condition) {
                    ($processed > 0) ? $toBeCalculated = $newPrice : $toBeCalculated = $originalPrice;
                    $newPrice = $condition->applyCondition($toBeCalculated);
                    $processed++;
                }
            } else {
                $newPrice = $conditions->applyCondition($originalPrice);
            }

            return Helpers::formatValue($newPrice, $formatted, $this->config);
        }
        return Helpers::formatValue($originalPrice, $formatted, $this->config);
    }

    /**
     * get the sum of price in which conditions are already applied
     * @param bool $formatted
     * @return float|int|string
     */
    public function getPriceSumWithConditions($formatted = true)
    {
        $quantity = (float) $this->get('quantity');

        return Helpers::formatValue($this->getPriceWithConditions(false) * $quantity, $formatted, $this->config);
    }
}
