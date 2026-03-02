<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use InvalidArgumentException;
use Lalalili\ShoppingCart\Adapters\LaravelEventDispatcherAdapter;
use Lalalili\ShoppingCart\Adapters\NullEventDispatcher;
use Lalalili\ShoppingCart\Adapters\NullStorageDriver;
use Lalalili\ShoppingCart\Adapters\SessionStorageDriver;
use Lalalili\ShoppingCart\Contracts\EventDispatcherInterface;
use Lalalili\ShoppingCart\Contracts\StorageDriverInterface;
use Lalalili\ShoppingCart\Exceptions\InvalidConditionException;
use Lalalili\ShoppingCart\Exceptions\InvalidItemException;
use Lalalili\ShoppingCart\Exceptions\UnknownModelException;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\Services\CartConditionService;
use Lalalili\ShoppingCart\Services\CartEventBridge;
use Lalalili\ShoppingCart\Services\CartItemService;
use Lalalili\ShoppingCart\Services\CartTotalsService;
use Lalalili\ShoppingCart\Validators\CartItemValidator;

/**
 * @phpstan-type CartConfig array{
 *   format_numbers: bool,
 *   decimals: int,
 *   dec_point: string,
 *   thousands_sep: string,
 *   storage?: class-string|null,
 *   events?: class-string|null
 * }
 * @phpstan-type QuantityUpdate array{relative?: bool, value: int|float|string}
 * @phpstan-type CartItemData array{
 *   id: int|string,
 *   name: string,
 *   price: int|float,
 *   quantity: int|float,
 *   attributes: ItemAttributeCollection,
 *   conditions: CartCondition|array<int, CartCondition>,
 *   associatedModel?: non-empty-string
 * }
 */
class Cart
{
    protected mixed $session;

    protected mixed $events;

    protected string $instanceName;

    protected string $sessionKey;

    protected string $sessionKeyCartItems;

    protected string $sessionKeyCartConditions;

    /**
     * @var CartConfig
     */
    protected array $config;

    protected int|string|null $currentItemId;

    protected StorageDriverInterface $storageDriver;

    protected EventDispatcherInterface $eventDispatcher;

    protected CartEventBridge $eventBridge;

    protected CartItemService $itemService;

    protected CartConditionService $conditionService;

    protected CartTotalsService $totalsService;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        mixed $session,
        mixed $events,
        string $instanceName,
        string $sessionKey,
        array $config
    ) {
        $this->events = $events;
        $this->session = $session;
        $this->instanceName = $instanceName;
        $this->sessionKey = $sessionKey;
        $this->sessionKeyCartItems = $this->sessionKey . '_cart_items';
        $this->sessionKeyCartConditions = $this->sessionKey . '_cart_conditions';

        /** @var CartConfig $resolvedConfig */
        $resolvedConfig = array_merge([
            'format_numbers' => false,
            'decimals' => 0,
            'dec_point' => '.',
            'thousands_sep' => ',',
        ], $config);

        $this->config = $resolvedConfig;
        $this->currentItemId = null;

        $this->storageDriver = $this->resolveStorageDriver($session);
        $this->eventDispatcher = $this->resolveEventDispatcher($events);

        $this->itemService = new CartItemService();
        $this->conditionService = new CartConditionService();
        $this->totalsService = new CartTotalsService();
        $this->eventBridge = new CartEventBridge($this->eventDispatcher, $this->instanceName, $this);

        $this->fireEvent('created');
    }

    public function session(string $sessionKey): self
    {
        if ($sessionKey === '') {
            throw new InvalidArgumentException('Session key is required.');
        }

        $this->sessionKey = $sessionKey;
        $this->sessionKeyCartItems = $this->sessionKey . '_cart_items';
        $this->sessionKeyCartConditions = $this->sessionKey . '_cart_conditions';

        return $this;
    }

    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * @param int|string $itemId
     */
    public function get($itemId): mixed
    {
        return $this->getContent()->get($itemId);
    }

    /**
     * @param int|string $itemId
     */
    public function has($itemId): bool
    {
        return $this->getContent()->has($itemId);
    }

    /**
     * @param int|string|array<int, mixed>|array<string, mixed> $id
     * @param array<int|string, mixed> $attributes
     * @param CartCondition|array<int, CartCondition> $conditions
     * @throws InvalidItemException
     */
    public function add(
        $id,
        mixed $name = null,
        mixed $price = null,
        mixed $quantity = null,
        $attributes = [],
        $conditions = [],
        mixed $associatedModel = null
    ): self {
        if (is_array($id)) {
            if (Helpers::isMultiArray($id)) {
                foreach ($id as $item) {
                    if (is_array($item)) {
                        $this->addFromArray($item);
                    }
                }

                return $this;
            }

            $this->addFromArray($id);

            return $this;
        }

        if (!is_int($id) && !is_string($id)) {
            $id = (string) $id;
        }

        /** @var CartItemData $data */
        $data = [
            'id' => $id,
            'name' => Helpers::toString($name),
            'price' => Helpers::normalizePrice($price),
            'quantity' => Helpers::toFloat($quantity),
            'attributes' => new ItemAttributeCollection(is_array($attributes) ? $attributes : []),
            'conditions' => $conditions instanceof CartCondition ? $conditions : (is_array($conditions) ? $conditions : []),
        ];

        if (is_string($associatedModel) && $associatedModel !== '') {
            $data['associatedModel'] = $associatedModel;
        }

        $item = $this->validate($data);
        $cart = $this->getContent();

        if ($cart->has($id)) {
            $this->update($id, $item);
        } else {
            $this->addRow($id, $item);
        }

        $this->currentItemId = $id;

        return $this;
    }

    /**
     * @param int|string $id
     * @param array<string, mixed> $data
     */
    public function update($id, $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if ($this->fireEvent('updating', $data) === false) {
            return false;
        }

        $cart = $this->getContent();
        $item = $cart->pull($id);

        if ($item === null) {
            return false;
        }

        /** @var array<string, mixed> $itemData */
        $itemData = $item instanceof ItemCollection ? $item->toArray() : (array) $item;

        foreach ($data as $key => $value) {
            $field = (string) $key;

            if ($field === 'quantity') {
                if (is_array($value)) {
                    /** @var QuantityUpdate $quantityData */
                    $quantityData = $value;

                    if (($quantityData['relative'] ?? false) === true) {
                        $itemData = $this->itemService->updateQuantityRelative($itemData, $field, $quantityData['value']);
                    } else {
                        $itemData = $this->itemService->updateQuantityNotRelative($itemData, $field, $quantityData['value']);
                    }
                } else {
                    $itemData = $this->itemService->updateQuantityRelative($itemData, $field, $value);
                }

                continue;
            }

            if ($field === 'attributes') {
                $itemData[$field] = new ItemAttributeCollection(is_array($value) ? $value : []);

                continue;
            }

            $itemData[$field] = $value;
        }

        $cart->put($id, new ItemCollection($itemData, $this->config));
        $this->save($cart);

        $this->fireEvent('updated', $itemData);

        return true;
    }

    /**
     * @param int|string $productId
     */
    public function addItemCondition($productId, mixed $itemCondition): self
    {
        if (!$itemCondition instanceof CartCondition) {
            return $this;
        }

        $product = $this->get($productId);

        if (!$product instanceof ItemCollection) {
            return $this;
        }

        $itemConditionTempHolder = $product['conditions'] ?? [];

        if (is_array($itemConditionTempHolder)) {
            $itemConditionTempHolder[] = $itemCondition;
        } else {
            $itemConditionTempHolder = $itemCondition;
        }

        $this->update($productId, [
            'conditions' => $itemConditionTempHolder,
        ]);

        return $this;
    }

    /**
     * @param int|string $id
     */
    public function remove($id): bool
    {
        $cart = $this->getContent();

        if ($this->fireEvent('removing', $id) === false) {
            return false;
        }

        $cart->forget($id);
        $this->save($cart);

        $this->fireEvent('removed', $id);

        return true;
    }

    public function clear(): bool
    {
        if ($this->fireEvent('clearing') === false) {
            return false;
        }

        $this->storageDriver->put($this->sessionKeyCartItems, []);

        $this->fireEvent('cleared');

        return true;
    }

    /**
     * @param CartCondition|array<int, CartCondition> $condition
     * @throws InvalidConditionException
     */
    public function condition(mixed $condition): self
    {
        if (is_array($condition)) {
            foreach ($condition as $singleCondition) {
                $this->condition($singleCondition);
            }

            return $this;
        }

        if (!$condition instanceof CartCondition) {
            throw new InvalidConditionException("Argument 1 must be an instance of 'Lalalili\\ShoppingCart\\CartCondition'");
        }

        $conditions = $this->conditionService->appendAndSort($this->getConditions(), $condition);
        $this->saveConditions($conditions);

        return $this;
    }

    public function getConditions(): CartConditionCollection
    {
        $conditions = $this->storageDriver->get($this->sessionKeyCartConditions, []);

        if ($conditions instanceof CartConditionCollection) {
            return $conditions;
        }

        if ($conditions instanceof \Illuminate\Support\Collection) {
            $conditions = $conditions->all();
        }

        if (!is_array($conditions)) {
            $conditions = [];
        }

        return new CartConditionCollection($this->normalizeStoredConditions($conditions));
    }

    /**
     * @param int|string $conditionName
     */
    public function getCondition($conditionName): ?CartCondition
    {
        $condition = $this->getConditions()->get($conditionName);

        return $condition instanceof CartCondition ? $condition : null;
    }

    public function getConditionsByType(mixed $type): CartConditionCollection
    {
        return $this->conditionService->filterByType($this->getConditions(), Helpers::toString($type));
    }

    public function removeConditionsByType(mixed $type): self
    {
        $this->getConditionsByType(Helpers::toString($type))->each(function (CartCondition $condition): void {
            $this->removeCartCondition($condition->getName());
        });

        return $this;
    }

    /**
     * @param int|string $conditionName
     */
    public function removeCartCondition($conditionName): void
    {
        $conditions = $this->getConditions();
        $conditions->pull((string) $conditionName);

        $this->saveConditions($conditions);
    }

    /**
     * @param int|string $itemId
     * @param int|string $conditionName
     */
    public function removeItemCondition($itemId, $conditionName): bool
    {
        $content = $this->getContent();
        if (!$content->has($itemId)) {
            return false;
        }

        $item = $content->get($itemId);
        if (!$item instanceof ItemCollection) {
            return false;
        }
        $itemData = $this->itemService->removeConditionByName($item->toArray(), (string) $conditionName);

        $this->update($itemId, [
            'conditions' => $itemData['conditions'] ?? [],
        ]);

        return true;
    }

    /**
     * @param int|string $itemId
     */
    public function clearItemConditions($itemId): bool
    {
        $content = $this->getContent();
        if (!$content->has($itemId)) {
            return false;
        }

        $this->update($itemId, [
            'conditions' => [],
        ]);

        return true;
    }

    public function clearCartConditions(): void
    {
        $this->storageDriver->put($this->sessionKeyCartConditions, []);
    }

    public function getSubTotalWithoutConditions(bool $formatted = true): float|int|string
    {
        return $this->totalsService->subTotalWithoutConditions(
            $this->getContent(),
            (bool) $formatted,
            $this->config
        );
    }

    public function getSubTotal(bool $formatted = true): float|int|string
    {
        return $this->totalsService->subTotal(
            $this->getContent(),
            $this->getConditions(),
            (bool) $formatted,
            $this->config
        );
    }

    public function getTotal(mixed $formatted = null): float|int|string
    {
        $formatNumbers = $formatted === null ? (bool) $this->config['format_numbers'] : (bool) $formatted;

        return $this->totalsService->total(
            $this->getConditions(),
            (float) $this->getSubTotal(false),
            $formatNumbers,
            $this->config
        );
    }

    public function getTotalQuantity(): int
    {
        return $this->totalsService->totalQuantity($this->getContent());
    }

    public function getContent(): CartCollection
    {
        $items = $this->storageDriver->get($this->sessionKeyCartItems, []);

        if ($items instanceof CartCollection) {
            return $items;
        }

        if ($items instanceof \Illuminate\Support\Collection) {
            $items = $items->all();
        }

        if (!is_array($items)) {
            $items = [];
        }

        $content = new CartCollection();

        foreach ($items as $key => $item) {
            if ($item instanceof ItemCollection) {
                $content->put($key, $item);
            }
        }

        return $content;
    }

    public function isEmpty(): bool
    {
        return $this->getContent()->isEmpty();
    }

    /**
     * @param CartItemData $item
     * @return CartItemData
     * @throws InvalidItemException
     */
    protected function validate(array $item): array
    {
        $rules = [
            'id' => 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric|min:0.1',
            'name' => 'required',
        ];

        $validator = CartItemValidator::make($item, $rules);

        if ($validator->fails()) {
            throw new InvalidItemException($validator->errors()->first());
        }

        return $item;
    }

    /**
     * @param int|string $id
     * @param CartItemData $item
     */
    protected function addRow($id, array $item): bool
    {
        if ($this->fireEvent('adding', $item) === false) {
            return false;
        }

        $cart = $this->getContent();
        $cart->put($id, new ItemCollection($item, $this->config));

        $this->save($cart);
        $this->fireEvent('added', $item);

        return true;
    }

    protected function save(CartCollection $cart): void
    {
        $this->storageDriver->put($this->sessionKeyCartItems, $cart);
    }

    protected function saveConditions(CartConditionCollection $conditions): void
    {
        $this->storageDriver->put($this->sessionKeyCartConditions, $conditions);
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function itemHasConditions(array $item): bool
    {
        return $this->itemService->hasConditions($item);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function updateQuantityRelative(array $item, string $key, mixed $value): array
    {
        return $this->itemService->updateQuantityRelative($item, $key, $value);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function updateQuantityNotRelative(array $item, string $key, mixed $value): array
    {
        return $this->itemService->updateQuantityNotRelative($item, $key, $value);
    }

    public function setDecimals(int $decimals): self
    {
        $this->config['decimals'] = $decimals;

        return $this;
    }

    public function setDecPoint(string $decPoint): self
    {
        $this->config['dec_point'] = $decPoint;

        return $this;
    }

    public function setThousandsSep(string $thousandsSep): self
    {
        $this->config['thousands_sep'] = $thousandsSep;

        return $this;
    }

    protected function fireEvent(string $name, mixed $value = []): mixed
    {
        return $this->eventBridge->dispatch($name, $value);
    }

    public function associate(mixed $model): self
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        if ($this->currentItemId === null) {
            return $this;
        }

        $cart = $this->getContent();
        $item = $cart->pull($this->currentItemId);

        if ($item === null) {
            return $this;
        }

        $itemData = $item instanceof ItemCollection ? $item->toArray() : (array) $item;
        $itemData['associatedModel'] = $model;

        $cart->put($this->currentItemId, new ItemCollection($itemData, $this->config));

        $this->save($cart);

        return $this;
    }

    /**
     * @param array<int|string, mixed> $item
     * @throws InvalidItemException
     */
    private function addFromArray(array $item): void
    {
        $id = $item['id'] ?? '';
        if (!is_int($id) && !is_string($id)) {
            $id = Helpers::toString($id);
        }

        $attributes = isset($item['attributes']) && is_array($item['attributes'])
            ? $item['attributes']
            : [];
        $conditions = $item['conditions'] ?? [];
        if (is_array($conditions)) {
            $conditions = array_values(array_filter(
                $conditions,
                static fn (mixed $condition): bool => $condition instanceof CartCondition
            ));
        } elseif (!$conditions instanceof CartCondition) {
            $conditions = [];
        }
        $associatedModel = isset($item['associatedModel']) && is_string($item['associatedModel'])
            ? $item['associatedModel']
            : null;

        $this->add(
            $id,
            $item['name'] ?? null,
            $item['price'] ?? null,
            $item['quantity'] ?? null,
            $attributes,
            $conditions,
            $associatedModel
        );
    }

    /**
     * @param array<mixed> $conditions
     * @return array<string, CartCondition>
     */
    private function normalizeStoredConditions(array $conditions): array
    {
        $normalizedConditions = [];

        foreach ($conditions as $key => $condition) {
            if ($condition instanceof CartCondition) {
                $normalizedConditions[(string) $key] = $condition;
            }
        }

        return $normalizedConditions;
    }

    private function resolveStorageDriver(mixed $session): StorageDriverInterface
    {
        if (is_object($session) && method_exists($session, 'get') && method_exists($session, 'put')) {
            return new SessionStorageDriver($session);
        }

        return new NullStorageDriver();
    }

    private function resolveEventDispatcher(mixed $events): EventDispatcherInterface
    {
        if (is_object($events) && method_exists($events, 'dispatch')) {
            return new LaravelEventDispatcherAdapter($events);
        }

        return new NullEventDispatcher();
    }
}
