<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Helpers;

use Closure;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartContext;
use Lalalili\ShoppingCart\CartPipelineResult;
use Lalalili\ShoppingCart\Contracts\CartPipelineInterface;

class WarningPipeline implements CartPipelineInterface
{
    public function handle(Cart $cart, CartContext $context, Closure $next): CartPipelineResult
    {
        return CartPipelineResult::make(
            false,
            ['checkout requires inventory confirmation'],
            ['stage' => 'before_checkout', 'channel' => $context->get('channel')]
        )->merge($next($cart, $context));
    }
}
