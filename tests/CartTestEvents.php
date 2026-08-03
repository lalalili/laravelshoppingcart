<?php

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 1/16/2015
 * Time: 3:20 PM
 */
use Lalalili\ShoppingCart\Cart;
use Mockery as m;

require_once __DIR__ . '/helpers/SessionMock.php';

class CartTestEventsPestCase extends PHPUnit\Framework\TestCase
{
    public const CART_INSTANCE_NAME = 'shopping';
}

uses(CartTestEventsPestCase::class);

beforeEach(function (): void {

});

afterEach(function (): void {
    m::close();
});

test('event cart created', function (): void {
    $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $this->assertTrue(true);
});

test('event cart adding', function (): void {
    $events = m::mock('Illuminate\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.adding', m::type('array'), true);
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.added', m::type('array'), true);
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $cart->add(455, 'Sample Item', 100.99, 2, array());
    $this->assertTrue(true);
});

test('event cart adding multiple times', function (): void {
    $events = m::mock('Illuminate\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(2)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.adding', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(2)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.added', m::type('array'), true);
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $cart->add(455, 'Sample Item 1', 100.99, 2, array());
    $cart->add(562, 'Sample Item 2', 100.99, 2, array());
    $this->assertTrue(true);
});

test('event cart adding multiple times scenario two', function (): void {
    $events = m::mock('Illuminate\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.adding', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.added', m::type('array'), true);
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 4, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 4, 'attributes' => array()), array('id' => 856, 'name' => 'Sample Item 3', 'price' => 50.25, 'quantity' => 4, 'attributes' => array()));
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $cart->add($items);
    $this->assertTrue(true);
});

test('event cart remove item', function (): void {
    $events = m::mock('Illuminate\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.adding', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.added', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(1)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.removing', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(1)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.removed', m::type('array'), true);
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 4, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 4, 'attributes' => array()), array('id' => 856, 'name' => 'Sample Item 3', 'price' => 50.25, 'quantity' => 4, 'attributes' => array()));
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $cart->add($items);
    $cart->remove(456);
    $this->assertTrue(true);
});

test('event cart clear', function (): void {
    $events = m::mock('Illuminate\Events\Dispatcher');
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.created', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.adding', m::type('array'), true);
    $events->shouldReceive('dispatch')->times(3)->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.added', m::type('array'), true);
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.clearing', m::type('array'), true);
    $events->shouldReceive('dispatch')->once()->with(CartTestEventsPestCase::CART_INSTANCE_NAME . '.cleared', m::type('array'), true);
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 4, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 4, 'attributes' => array()), array('id' => 856, 'name' => 'Sample Item 3', 'price' => 50.25, 'quantity' => 4, 'attributes' => array()));
    $cart = new Cart(new SessionMock(), $events, CartTestEventsPestCase::CART_INSTANCE_NAME, 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
    $cart->add($items);
    $cart->clear();
    $this->assertTrue(true);
});
