<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Tests\Helpers;

use Lalalili\ShoppingCart\Contracts\AssociatedModelResolverInterface;
use Lalalili\ShoppingCart\ItemCollection;

class StaticAssociatedModelResolver implements AssociatedModelResolverInterface
{
    public function resolve(ItemCollection $item, mixed $associatedModel, mixed $id): mixed
    {
        return new MockProduct($id, 'Resolved ' . (string) $associatedModel, 10.0);
    }
}
