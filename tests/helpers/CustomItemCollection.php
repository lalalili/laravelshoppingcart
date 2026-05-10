<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Helpers;

use Lalalili\ShoppingCart\ItemCollection;

class CustomItemCollection extends ItemCollection
{
    public function marker(): string
    {
        return 'custom-item-collection';
    }
}
