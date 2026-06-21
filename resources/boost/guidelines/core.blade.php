## Laravel Shopping Cart

`lalalili/laravelshoppingcart` provides reusable cart instances, item storage adapters, and cart events for Laravel hosts.

### Named Instances

- Configure host carts in `config/shopping_cart.php` under `instances`.
- Use `Lalalili\ShoppingCart\CartFactory` when creating app-specific cart bindings.
- Keep host-specific cart subclasses, product lookups, promotion refreshes, and checkout rules in the host app.
- Use distinct `instance_name` and `session_key` values for cart, wishlist, and checkout carts.

### Boundaries

- Do not import host `Product`, `Order`, promotion, or checkout classes into this package.
- Keep storage adapters generic; pass host-specific constructor arguments through `storage_parameters`.
- Register additional cart bindings without overriding existing host bindings.
