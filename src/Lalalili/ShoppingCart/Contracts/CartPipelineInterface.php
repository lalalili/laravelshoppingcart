<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Contracts;

use Closure;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartContext;
use Lalalili\ShoppingCart\CartPipelineResult;

interface CartPipelineInterface
{
    /**
     * @param Closure(Cart, CartContext): CartPipelineResult $next
     */
    public function handle(Cart $cart, CartContext $context, Closure $next): CartPipelineResult;
}
