<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Closure;
use InvalidArgumentException;
use Lalalili\ShoppingCart\Adapters\LaravelEventDispatcherAdapter;
use Lalalili\ShoppingCart\Adapters\NullEventDispatcher;
use Lalalili\ShoppingCart\Adapters\NullStorageDriver;
use Lalalili\ShoppingCart\Adapters\SessionStorageDriver;
use Lalalili\ShoppingCart\Contracts\CartPipelineInterface;
use Lalalili\ShoppingCart\Contracts\EventDispatcherInterface;
use Lalalili\ShoppingCart\Contracts\StorageDriverInterface;
use Lalalili\ShoppingCart\Exceptions\InvalidConditionException;
use Lalalili\ShoppingCart\Exceptions\InvalidItemException;
use Lalalili\ShoppingCart\Exceptions\StaleCartException;
use Lalalili\ShoppingCart\Exceptions\UnknownModelException;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\Services\CartConditionService;
use Lalalili\ShoppingCart\Services\CartEventBridge;
use Lalalili\ShoppingCart\Services\CartItemService;
use Lalalili\ShoppingCart\Services\CartSnapshotService;
use Lalalili\ShoppingCart\Services\CartTotalsService;
use Lalalili\ShoppingCart\Validators\CartItemValidator;

/**
 * @phpstan-type CartConfig array{
 *   format_numbers: bool,
 *   decimals: int,
 *   dec_point: string,
 *   thousands_sep: string,
     *   storage?: class-string|null,
     *   events?: class-string|null,
 *   item_collection_class?: class-string<ItemCollection>|null,
 *   associated_model_resolver?: mixed,
 *   rounding?: array<string, mixed>,
 *   context?: array<string, mixed>,
 *   pipelines?: array<string, mixed>
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

    protected string $sessionKeyCartContext;

    protected string $sessionKeyCartVersion;

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

    protected CartSnapshotService $snapshotService;

    /**
     * @var array<string, float|int|string>
     */
    private array $totalsCache = [];

    /**
     * @var array<string, string>
     */
    private array $processedPipelineSignatures = [];

    /**
     * @var array<string, bool>
     */
    private array $runningPipelines = [];

    /**
     * @var array<string, CartPipelineResult>
     */
    private array $pipelineResults = [];

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
        $this->sessionKeyCartContext = $this->sessionKey . '_cart_context';
        $this->sessionKeyCartVersion = $this->sessionKey . '_cart_version';

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
        $this->snapshotService = new CartSnapshotService();
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
        $this->sessionKeyCartContext = $this->sessionKey . '_cart_context';
        $this->sessionKeyCartVersion = $this->sessionKey . '_cart_version';
        $this->clearTotalsCache();
        $this->processedPipelineSignatures = [];
        $this->pipelineResults = [];

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
     * @param array<int, array<int|string, mixed>> $items
     * @throws InvalidItemException
     */
    public function addMany(array $items): self
    {
        $cart = $this->getContent();
        $afterSaveEvents = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            [$id, $validatedItem] = $this->normalizeItemArray($item);

            if ($cart->has($id)) {
                if ($this->fireEvent('updating', $validatedItem) === false) {
                    continue;
                }

                $existing = $cart->pull($id);
                $itemData = $existing instanceof ItemCollection ? $existing->toArray() : (array) $existing;
                $itemData = $this->applyUpdateData($itemData, $validatedItem);

                $cart->put($id, $this->makeItemCollection($itemData));
                $afterSaveEvents[] = ['updated', $itemData];
            } else {
                if ($this->fireEvent('adding', $validatedItem) === false) {
                    continue;
                }

                $cart->put($id, $this->makeItemCollection($validatedItem));
                $afterSaveEvents[] = ['added', $validatedItem];
            }

            $this->currentItemId = $id;
        }

        $this->save($cart);

        foreach ($afterSaveEvents as [$eventName, $payload]) {
            $this->fireEvent($eventName, $payload);
        }

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
        $itemData = $this->applyUpdateData($itemData, $data);

        $cart->put($id, $this->makeItemCollection($itemData));
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

    /**
     * @param array<int, int|string> $ids
     */
    public function removeMany(array $ids): bool
    {
        $cart = $this->getContent();
        $removedIds = [];

        foreach ($ids as $id) {
            if (!$cart->has($id)) {
                continue;
            }

            if ($this->fireEvent('removing', $id) === false) {
                continue;
            }

            $cart->forget($id);
            $removedIds[] = $id;
        }

        if ($removedIds === []) {
            return false;
        }

        $this->save($cart);

        foreach ($removedIds as $removedId) {
            $this->fireEvent('removed', $removedId);
        }

        return true;
    }

    public function clear(): bool
    {
        if ($this->fireEvent('clearing') === false) {
            return false;
        }

        $this->storageDriver->put($this->sessionKeyCartItems, []);
        $this->touchCartVersion();
        $this->clearTotalsCache();

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
        $condition = $this->getConditions()->get((string) $conditionName);

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
        $this->touchCartVersion();
        $this->clearTotalsCache();
    }

    public function getSubTotalWithoutConditions(bool $formatted = true): float|int|string
    {
        $this->runConfiguredPipelinesBeforeTotals();

        $cacheKey = 'subtotal_without_conditions:' . (int) $formatted;

        if (array_key_exists($cacheKey, $this->totalsCache)) {
            return $this->totalsCache[$cacheKey];
        }

        return $this->totalsCache[$cacheKey] = $this->totalsService->subTotalWithoutConditions(
            $this->getContent(),
            (bool) $formatted,
            $this->config
        );
    }

    public function getSubTotal(bool $formatted = true): float|int|string
    {
        $this->runConfiguredPipelinesBeforeTotals();

        $cacheKey = 'subtotal:' . (int) $formatted;

        if (array_key_exists($cacheKey, $this->totalsCache)) {
            return $this->totalsCache[$cacheKey];
        }

        return $this->totalsCache[$cacheKey] = $this->totalsService->subTotal(
            $this->getContent(),
            $this->getConditions(),
            (bool) $formatted,
            $this->config
        );
    }

    public function getTotal(mixed $formatted = null): float|int|string
    {
        $this->runConfiguredPipelinesBeforeTotals();

        $formatNumbers = $formatted === null ? (bool) $this->config['format_numbers'] : (bool) $formatted;
        $cacheKey = 'total:' . (int) $formatNumbers;

        if (array_key_exists($cacheKey, $this->totalsCache)) {
            return $this->totalsCache[$cacheKey];
        }

        return $this->totalsCache[$cacheKey] = $this->totalsService->total(
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
            } elseif (is_array($item)) {
                $content->put($key, $this->makeItemCollection($item));
            }
        }

        return $content;
    }

    public function isEmpty(): bool
    {
        return $this->getContent()->isEmpty();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(bool $formatted = false): array
    {
        $this->runConfiguredPipelinesBeforeTotals();

        $snapshot = $this->snapshotService->snapshot(
            $this->getContent(),
            $this->getConditions(),
            $this->getContext(),
            $this->version(),
            $this->instanceName,
            $this->sessionKey,
            $formatted,
            $this->config,
            $this->hash()
        );

        $snapshot['pipelines'] = $this->pipelineResults();

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    public function explainTotals(bool $formatted = false): array
    {
        $this->runConfiguredPipelinesBeforeTotals();

        return $this->snapshotService->explainTotals(
            $this->getContent(),
            $this->getConditions(),
            $this->getContext(),
            $formatted,
            $this->config
        );
    }

    /**
     * @param array<string, mixed>|CartContext $context
     */
    public function withContext(array|CartContext $context): self
    {
        $this->storageDriver->put($this->sessionKeyCartContext, CartContext::from($context)->toArray());
        $this->touchCartVersion();
        $this->clearTotalsCache();

        return $this;
    }

    public function getContext(): CartContext
    {
        $context = $this->storageDriver->get($this->sessionKeyCartContext, null);

        if ($context instanceof CartContext) {
            return $context;
        }

        if ($context instanceof \Illuminate\Support\Collection) {
            $context = $context->all();
        }

        if (!is_array($context)) {
            $configuredContext = $this->config['context']['defaults'] ?? [];
            $context = is_array($configuredContext) ? $configuredContext : [];
        }

        return new CartContext($context);
    }

    public function version(): int|string|null
    {
        $version = $this->storageDriver->get($this->sessionKeyCartVersion, 0);

        if (is_int($version) || is_string($version)) {
            return $version;
        }

        return null;
    }

    public function hash(): string
    {
        $payload = $this->snapshotService->hashPayload(
            $this->getContent(),
            $this->getConditions(),
            $this->getContext(),
            $this->version()
        );

        return hash('sha256', json_encode($this->normalizeForHash($payload), JSON_THROW_ON_ERROR));
    }

    public function assertHash(string $expectedHash): void
    {
        if (!hash_equals($expectedHash, $this->hash())) {
            throw new StaleCartException('The cart has changed since the supplied hash was generated.');
        }
    }

    public function runPipelines(string $stage, ?CartContext $context = null): CartPipelineResult
    {
        $pipelines = $this->configuredPipelines($stage);

        if ($pipelines === [] || ($this->runningPipelines[$stage] ?? false)) {
            return CartPipelineResult::empty();
        }

        $signature = $stage . ':' . $this->hash();
        if (($this->processedPipelineSignatures[$stage] ?? null) === $signature) {
            return $this->pipelineResults[$stage] ?? CartPipelineResult::empty();
        }

        $this->runningPipelines[$stage] = true;

        try {
            $runner = array_reduce(
                array_reverse($pipelines),
                fn (Closure $next, mixed $pipeline): Closure => function (Cart $cart, CartContext $context) use ($pipeline, $next): CartPipelineResult {
                    $callable = $this->resolvePipeline($pipeline);

                    if ($callable === null) {
                        return $next($cart, $context);
                    }

                    return $callable($cart, $context, $next);
                },
                static fn (Cart $cart, CartContext $context): CartPipelineResult => CartPipelineResult::empty()
            );

            $result = $runner($this, $context ?? $this->getContext());
            $this->processedPipelineSignatures[$stage] = $stage . ':' . $this->hash();
            $this->pipelineResults[$stage] = $result;

            return $result;
        } finally {
            $this->runningPipelines[$stage] = false;
        }
    }

    /**
     * @return array<string, array{changed: bool, warnings: list<string>, metadata: array<string, mixed>}>
     */
    public function pipelineResults(): array
    {
        $results = [];

        foreach ($this->pipelineResults as $stage => $result) {
            $results[$stage] = $result->toArray();
        }

        return $results;
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
        $cart->put($id, $this->makeItemCollection($item));

        $this->save($cart);
        $this->fireEvent('added', $item);

        return true;
    }

    protected function save(CartCollection $cart): void
    {
        $this->storageDriver->put($this->sessionKeyCartItems, $cart);
        $this->touchCartVersion();
        $this->clearTotalsCache();
    }

    protected function saveConditions(CartConditionCollection $conditions): void
    {
        $this->storageDriver->put($this->sessionKeyCartConditions, $conditions);
        $this->touchCartVersion();
        $this->clearTotalsCache();
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
        $this->clearTotalsCache();

        return $this;
    }

    public function setDecPoint(string $decPoint): self
    {
        $this->config['dec_point'] = $decPoint;
        $this->clearTotalsCache();

        return $this;
    }

    public function setThousandsSep(string $thousandsSep): self
    {
        $this->config['thousands_sep'] = $thousandsSep;
        $this->clearTotalsCache();

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

        $cart->put($this->currentItemId, $this->makeItemCollection($itemData));

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
     * @param array<int|string, mixed> $item
     * @return array{0: int|string, 1: CartItemData}
     * @throws InvalidItemException
     */
    private function normalizeItemArray(array $item): array
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

        /** @var CartItemData $data */
        $data = [
            'id' => $id,
            'name' => Helpers::toString($item['name'] ?? null),
            'price' => Helpers::normalizePrice($item['price'] ?? null),
            'quantity' => Helpers::toFloat($item['quantity'] ?? null),
            'attributes' => new ItemAttributeCollection($attributes),
            'conditions' => $conditions,
        ];

        $associatedModel = $item['associatedModel'] ?? null;
        if (is_string($associatedModel) && $associatedModel !== '') {
            $data['associatedModel'] = $associatedModel;
        }

        return [$id, $this->validate($data)];
    }

    /**
     * @param array<string, mixed> $itemData
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyUpdateData(array $itemData, array $data): array
    {
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

        return $itemData;
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function makeItemCollection(array $item): ItemCollection
    {
        $collectionClass = $this->config['item_collection_class'] ?? ItemCollection::class;

        if (
            is_string($collectionClass)
            && class_exists($collectionClass)
            && is_subclass_of($collectionClass, ItemCollection::class)
        ) {
            return new $collectionClass($item, $this->config);
        }

        return new ItemCollection($item, $this->config);
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

    private function clearTotalsCache(): void
    {
        $this->totalsCache = [];
    }

    private function touchCartVersion(): void
    {
        $version = $this->version();

        try {
            $this->storageDriver->put($this->sessionKeyCartVersion, is_int($version) ? $version + 1 : 1);
        } catch (\Throwable) {
            // Some legacy storage adapters only accept cart payloads. The hash still reflects cart content.
        }

        $this->processedPipelineSignatures = [];
        $this->pipelineResults = [];
    }

    protected function runConfiguredPipelinesBeforeTotals(): void
    {
        $pipelines = $this->config['pipelines'] ?? [];
        $autoRun = is_array($pipelines) ? ($pipelines['auto_run_before_totals'] ?? true) : true;

        if ($autoRun !== false) {
            $this->runPipelines('before_totals');
        }
    }

    /**
     * @return list<mixed>
     */
    private function configuredPipelines(string $stage): array
    {
        $pipelines = $this->config['pipelines'][$stage] ?? [];

        if ($pipelines === '') {
            return [];
        }

        return is_array($pipelines) ? array_values($pipelines) : [$pipelines];
    }

    /**
     * @return Closure(Cart, CartContext, Closure): CartPipelineResult|null
     */
    private function resolvePipeline(mixed $pipeline): ?Closure
    {
        if (is_string($pipeline) && class_exists($pipeline)) {
            $pipeline = function_exists('app') ? app($pipeline) : new $pipeline();
        }

        if ($pipeline instanceof CartPipelineInterface) {
            return static fn (Cart $cart, CartContext $context, Closure $next): CartPipelineResult => $pipeline->handle($cart, $context, $next);
        }

        if (is_object($pipeline) && method_exists($pipeline, 'handle')) {
            return static function (Cart $cart, CartContext $context, Closure $next) use ($pipeline): CartPipelineResult {
                $result = $pipeline->handle($cart, $context, $next);

                return $result instanceof CartPipelineResult ? $result : CartPipelineResult::empty();
            };
        }

        if (is_callable($pipeline)) {
            return static function (Cart $cart, CartContext $context, Closure $next) use ($pipeline): CartPipelineResult {
                $result = $pipeline($cart, $context, $next);

                return $result instanceof CartPipelineResult ? $result : CartPipelineResult::empty();
            };
        }

        return null;
    }

    private function normalizeForHash(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $isList = array_keys($value) === range(0, count($value) - 1);

        if (!$isList) {
            ksort($value);
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->normalizeForHash($child);
        }

        return $value;
    }
}
