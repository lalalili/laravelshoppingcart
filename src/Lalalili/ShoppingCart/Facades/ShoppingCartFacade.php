<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Facades;

use Illuminate\Support\Facades\Facade;

class ShoppingCartFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shopping_cart';
    }
}
