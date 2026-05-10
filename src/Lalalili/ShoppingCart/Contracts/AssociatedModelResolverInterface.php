<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Contracts;

use Lalalili\ShoppingCart\ItemCollection;

interface AssociatedModelResolverInterface
{
    public function resolve(ItemCollection $item, mixed $associatedModel, mixed $id): mixed;
}
