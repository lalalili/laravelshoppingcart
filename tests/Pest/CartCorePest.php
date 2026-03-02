<?php

declare(strict_types=1);

use Lalalili\ShoppingCart\Cart;
use Mockery as m;

require_once __DIR__ . '/../helpers/SessionMock.php';

beforeEach(function (): void {
    $events = m::mock('Illuminate\\Contracts\\Events\\Dispatcher');
    $events->shouldReceive('dispatch');

    $this->cart = new Cart(
        new SessionMock(),
        $events,
        'pest',
        'PEST_SESSION_KEY',
        require __DIR__ . '/../helpers/configMock.php'
    );
});

afterEach(function (): void {
    m::close();
});

it('keeps subtotal and total aligned when no cart-level conditions are set', function (): void {
    $this->cart->add(1001, 'Pest Item', 99.50, 2, []);

    expect((float) $this->cart->getSubTotal(false))->toBe((float) $this->cart->getTotal(false));
});
