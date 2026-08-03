<?php

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 3/18/2015
 * Time: 6:17 PM
 */
use Lalalili\ShoppingCart\Cart;
use Mockery as m;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\Tests\Helpers\MockProduct;
use Lalalili\ShoppingCart\Tests\Helpers\StaticAssociatedModelResolver;

require_once __DIR__ . '/helpers/SessionMock.php';

class ItemTestPestCase extends PHPUnit\Framework\TestCase
{
    /**
     * @var Lalalili\ShoppingCart\Cart
     */
    protected $cart;
}

uses(ItemTestPestCase::class);

beforeEach(function (): void {
    $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
    $events->shouldReceive('dispatch');
    $this->cart = new Cart(new SessionMock(), $events, 'shopping', 'SAMPLESESSIONKEY', require __DIR__ . '/helpers/configMock.php');
});

afterEach(function (): void {
    m::close();
});

test('item get sum price using property', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEquals(201.98, $item->getPriceSum(), 'Item summed price should be 201.98');
});

test('item get sum price using array style', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEquals(201.98, $item->getPriceSum(), 'Item summed price should be 201.98');
});

test('item get conditions empty', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEmpty($item->getConditions(), 'Item should have no conditions');
});

test('item get conditions with conditions', function (): void {
    $itemCondition1 = new \Lalalili\ShoppingCart\CartCondition(array('name' => 'SALE 5%', 'type' => 'sale', 'target' => 'item', 'value' => '-5%'));
    $itemCondition2 = new CartCondition(array('name' => 'Item Gift Pack 25.00', 'type' => 'promo', 'target' => 'item', 'value' => '-25'));
    $this->cart->add(455, 'Sample Item', 100.99, 2, array(), [$itemCondition1, $itemCondition2]);
    $item = $this->cart->get(455);
    $this->assertCount(2, $item->getConditions(), 'Item should have two conditions');
});

test('item associate model', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array())->associate(MockProduct::class);
    $item = $this->cart->get(455);
    $this->assertEquals(MockProduct::class, $item->associatedModel, 'Item assocaited model should be ' . MockProduct::class);
});

test('it will throw an exception when a non existing model is being associated', function (): void {
    $this->expectException(\Lalalili\ShoppingCart\Exceptions\UnknownModelException::class);
    $this->expectExceptionMessage('The supplied model SomeModel does not exist.');
    $this->cart->add(1, 'Test item', 1, 10.0)->associate('SomeModel');
});

test('item get model', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array())->associate(MockProduct::class);
    $item = $this->cart->get(455);
    $this->assertInstanceOf(MockProduct::class, $item->model);
    $this->assertEquals('Sample Item', $item->model->name);
    $this->assertEquals(455, $item->model->id);
});

test('item get model can use configured resolver', function (): void {
    $config = require __DIR__ . '/helpers/configMock.php';
    $config['associated_model_resolver'] = StaticAssociatedModelResolver::class;
    $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
    $events->shouldReceive('dispatch');
    $cart = new Cart(new SessionMock(), $events, 'shopping', 'RESOLVER', $config);
    $cart->add(['id' => 455, 'name' => 'Sample Item', 'price' => 100.99, 'quantity' => 2, 'associatedModel' => 'Product']);
    $this->assertInstanceOf(MockProduct::class, $cart->get(455)->model);
    $this->assertEquals('Resolved Product', $cart->get(455)->model->name);
});

test('item get model will return null if it has no model', function (): void {
    $this->cart->add(455, 'Sample Item', 100.99, 2, array());
    $item = $this->cart->get(455);
    $this->assertEquals(null, $item->model);
});
