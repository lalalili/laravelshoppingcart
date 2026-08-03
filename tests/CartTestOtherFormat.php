<?php

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 1/12/2015
 * Time: 9:59 PM
 */
use Lalalili\ShoppingCart\Cart;
use Mockery as m;

require_once __DIR__ . '/helpers/SessionMock.php';

class CartTestOtherFormatPestCase extends PHPUnit\Framework\TestCase
{
    /**
     * @var Lalalili\ShoppingCart\Cart
     */
    protected $cart;
}

uses(CartTestOtherFormatPestCase::class);

beforeEach(function (): void {
    $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
    $events->shouldReceive('dispatch');
    $this->cart = new Cart(new SessionMock(), $events, 'shopping', 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMockOtherFormat.php');
});

afterEach(function (): void {
    m::close();
});

test('cart sub total', function (): void {
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 1, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 1, 'attributes' => array()), array('id' => 856, 'name' => 'Sample Item 3', 'price' => 50.25, 'quantity' => 1, 'attributes' => array()));
    $this->cart->add($items);
    $this->assertEquals('187,490', $this->cart->getSubTotal(), 'Cart should have sub total of 187,490');
    // if we remove an item, the sub total should be updated as well
    $this->cart->remove(456);
    $this->assertEquals('119,500', $this->cart->getSubTotal(), 'Cart should have sub total of 119,500');
});

test('sub total without conditions is not mangled by thousands separator', function (): void {
    // 回歸:getPriceSum() 未帶 formatted=false 時會回傳含千分位字串,
    // (float) 轉型後金額被截斷(例:"3.000" → 3.0)。
    $this->cart->add(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 1500, 'quantity' => 2, 'attributes' => array()));
    $this->assertSame(3000.0, $this->cart->getSubTotalWithoutConditions(false));
    $this->assertSame(3000.0, (float) $this->cart->getContent()->get(456)->getPriceSum(false));
});

test('sub total when item quantity is updated', function (): void {
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 3, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 1, 'attributes' => array()));
    $this->cart->add($items);
    $this->assertEquals('273,220', $this->cart->getSubTotal(), 'Cart should have sub total of 273.22');
    // when cart's item quantity is updated, the subtotal should be updated as well
    $this->cart->update(456, array('quantity' => 2));
    $this->assertEquals('409,200', $this->cart->getSubTotal(), 'Cart should have sub total of 409.2');
});

test('sub total when item quantity is updated by reduced', function (): void {
    $items = array(array('id' => 456, 'name' => 'Sample Item 1', 'price' => 67.98999999999999, 'quantity' => 3, 'attributes' => array()), array('id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 1, 'attributes' => array()));
    $this->cart->add($items);
    $this->assertEquals('273,220', $this->cart->getSubTotal(), 'Cart should have sub total of 273.22');
    // when cart's item quantity is updated, the subtotal should be updated as well
    $this->cart->update(456, array('quantity' => -1));
    // get the item to be evaluated
    $item = $this->cart->get(456);
    $this->assertEquals(2, $item['quantity'], 'Item quantity of with item ID of 456 should now be reduced to 2');
    $this->assertEquals('205,230', $this->cart->getSubTotal(), 'Cart should have sub total of 205.23');
});
