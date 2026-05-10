<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Helpers;

use Closure;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\CartContext;
use Lalalili\ShoppingCart\CartPipelineResult;
use Lalalili\ShoppingCart\Contracts\CartPipelineInterface;

class AddDiscountPipeline implements CartPipelineInterface
{
    public function handle(Cart $cart, CartContext $context, Closure $next): CartPipelineResult
    {
        if ($cart->getCondition('pipeline-discount') === null) {
            $cart->condition(new CartCondition([
                'name' => 'pipeline-discount',
                'type' => 'discount',
                'target' => 'total',
                'value' => '-10',
                'attributes' => [
                    'channel' => $context->get('channel'),
                ],
            ]));

            return CartPipelineResult::make(true, [], ['pipeline' => 'discount'])->merge($next($cart, $context));
        }

        return $next($cart, $context);
    }
}
