<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int|string, mixed>
 */
class ItemAttributeCollection extends Collection
{
    public function __get($name): mixed
    {
        if ($this->has($name)) {
            return $this->get($name);
        }

        return null;
    }
}
