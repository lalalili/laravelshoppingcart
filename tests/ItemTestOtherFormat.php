<?php

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 3/18/2015
 * Time: 6:17 PM
 */
use Lalalili\ShoppingCart\Cart;
use Mockery as m;

require_once __DIR__ . '/helpers/SessionMock.php';

class ItemTestOtherFormatPestCase extends PHPUnit\Framework\TestCase
{
    /**
     * @var Lalalili\ShoppingCart\Cart
     */
    protected $cart;
}

uses(ItemTestOtherFormatPestCase::class);

beforeEach(function (): void {
    $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
    $events->shouldReceive('dispatch');
    $this->cart = new Cart(new SessionMock(), $events, 'shopping', 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMockOtherFormat.php');
});

afterEach(function (): void {
    m::close();
});

test('item get sum price using property', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEquals('201,980', $item->getPriceSum(), 'Item summed price should be 201.98');
});

test('item get sum price using array style', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEquals('201,980', $item->getPriceSum(), 'Item summed price should be 201.98');
});
