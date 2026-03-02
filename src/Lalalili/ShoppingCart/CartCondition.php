<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Lalalili\ShoppingCart\Exceptions\InvalidConditionException;
use Lalalili\ShoppingCart\Helpers\Helpers;
use Lalalili\ShoppingCart\Validators\CartConditionValidator;

/**
 * @phpstan-type ConditionData array{
 *   name: string,
 *   type: string,
 *   value: int|float|string,
 *   target?: string,
 *   order?: int|numeric-string,
 *   attributes?: array<string, mixed>
 * }
 */
class CartCondition
{
    /**
     * @var ConditionData
     */
    private array $args;

    public float $parsedRawValue = 0.0;

    /**
     * @param array<string, mixed> $args
     * @throws InvalidConditionException
     */
    public function __construct(array $args)
    {
        if (Helpers::isMultiArray($args)) {
            throw new InvalidConditionException('Multi dimensional array is not supported.');
        }

        $this->validate($args);

        $rawValue = $args['value'] ?? '';

        /** @var ConditionData $conditionData */
        $conditionData = [
            'name' => Helpers::toString($args['name'] ?? ''),
            'type' => Helpers::toString($args['type'] ?? ''),
            'value' => is_int($rawValue) || is_float($rawValue) || is_string($rawValue)
                ? $rawValue
                : Helpers::toString($rawValue),
            'target' => Helpers::toString($args['target'] ?? ''),
            'order' => Helpers::toInt($args['order'] ?? 0),
            'attributes' => isset($args['attributes']) && is_array($args['attributes']) ? $args['attributes'] : [],
        ];

        $this->args = $conditionData;
    }

    public function getTarget(): string
    {
        return (string) ($this->args['target'] ?? '');
    }

    public function getName(): string
    {
        return $this->args['name'];
    }

    public function getType(): string
    {
        return $this->args['type'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        $attributes = $this->args['attributes'] ?? [];

        return is_array($attributes) ? $attributes : [];
    }

    public function getValue(): int|float|string
    {
        return $this->args['value'];
    }

    public function setOrder(int $order = 1): self
    {
        $this->args['order'] = $order;

        return $this;
    }

    public function getOrder(): int
    {
        return isset($this->args['order']) && is_numeric((string) $this->args['order'])
            ? (int) $this->args['order']
            : 0;
    }

    public function applyCondition(mixed $totalOrSubTotalOrPrice): float
    {
        return $this->apply($totalOrSubTotalOrPrice, $this->getValue());
    }

    public function getCalculatedValue(mixed $totalOrSubTotalOrPrice): float
    {
        $this->apply($totalOrSubTotalOrPrice, $this->getValue());

        return $this->parsedRawValue;
    }

    protected function apply(mixed $totalOrSubTotalOrPrice, mixed $conditionValue): float
    {
        $baseAmount = Helpers::toFloat($totalOrSubTotalOrPrice);
        $normalizedConditionValue = Helpers::toString($conditionValue);

        if ($this->valueIsPercentage($normalizedConditionValue)) {
            $percent = Helpers::toFloat(Helpers::normalizePrice($this->cleanValue($normalizedConditionValue)));
            $this->parsedRawValue = $baseAmount * ($percent / 100);

            if ($this->valueIsToBeSubtracted($normalizedConditionValue)) {
                $result = $baseAmount - $this->parsedRawValue;
            } else {
                $result = $baseAmount + $this->parsedRawValue;
            }

            return $result < 0 ? 0.0 : (float) $result;
        }

        $this->parsedRawValue = Helpers::toFloat(Helpers::normalizePrice($this->cleanValue($normalizedConditionValue)));

        if ($this->valueIsToBeSubtracted($normalizedConditionValue)) {
            $result = $baseAmount - $this->parsedRawValue;
        } else {
            $result = $baseAmount + $this->parsedRawValue;
        }

        return $result < 0 ? 0.0 : (float) $result;
    }

    protected function valueIsPercentage(string $value): bool
    {
        return preg_match('/%/', $value) === 1;
    }

    protected function valueIsToBeSubtracted(string $value): bool
    {
        return preg_match('/\-/', $value) === 1;
    }

    protected function valueIsToBeAdded(string $value): bool
    {
        return preg_match('/\+/', $value) === 1;
    }

    protected function cleanValue(string $value): string
    {
        return str_replace(['%', '-', '+'], '', $value);
    }

    /**
     * @param array<string, mixed> $args
     * @throws InvalidConditionException
     */
    protected function validate(array $args): void
    {
        $rules = [
            'name' => 'required',
            'type' => 'required',
            'value' => 'required',
        ];

        $validator = CartConditionValidator::make($args, $rules);

        if ($validator->fails()) {
            throw new InvalidConditionException($validator->errors()->first());
        }
    }
}
