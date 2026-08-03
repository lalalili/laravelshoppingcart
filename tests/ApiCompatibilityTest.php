<?php

declare (strict_types=1);
use Lalalili\ShoppingCart\Cart;

class ApiCompatibilityTestPestCase extends PHPUnit\Framework\TestCase
{
}

uses(ApiCompatibilityTestPestCase::class);

test('cart public api snapshot for v14x non breaking contract', function (): void {
    $expectedMethods = ['__construct', 'session', 'getInstanceName', 'get', 'has', 'add', 'addMany', 'update', 'addItemCondition', 'remove', 'removeMany', 'clear', 'condition', 'getConditions', 'getCondition', 'getConditionsByType', 'removeConditionsByType', 'removeCartCondition', 'removeItemCondition', 'clearItemConditions', 'clearCartConditions', 'getSubTotalWithoutConditions', 'batch', 'getSubTotal', 'getSubTotalAsInt', 'getTotal', 'getTotalAsInt', 'getTotalQuantity', 'getContent', 'isEmpty', 'snapshot', 'explainTotals', 'withContext', 'getContext', 'version', 'hash', 'assertHash', 'runPipelines', 'pipelineResults', 'setDecimals', 'setDecPoint', 'setThousandsSep', 'associate'];
    $publicMethods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), array_filter((new ReflectionClass(Cart::class))->getMethods(ReflectionMethod::IS_PUBLIC), static fn (ReflectionMethod $method): bool => $method->class === Cart::class));
    sort($expectedMethods);
    sort($publicMethods);
    $this->assertSame($expectedMethods, $publicMethods);
});
