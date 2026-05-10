<?php

/**
 * Created by PhpStorm.
 * User: darryl
 * Date: 1/12/2015
 * Time: 9:59 PM
 */

use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\Exceptions\StaleCartException;
use Lalalili\ShoppingCart\Tests\Helpers\AddDiscountPipeline;
use Mockery as m;
use Lalalili\ShoppingCart\Tests\Helpers\CustomItemCollection;
use Lalalili\ShoppingCart\Tests\Helpers\MockProduct;
use Lalalili\ShoppingCart\Tests\Helpers\WarningPipeline;

require_once __DIR__ . '/helpers/SessionMock.php';

class CartTest extends PHPUnit\Framework\TestCase
{
    /**
     * @var Lalalili\ShoppingCart\Cart
     */
    protected $cart;

    public function setUp(): void
    {
        $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
        $events->shouldReceive('dispatch');

        $this->cart = new Cart(
            new SessionMock(),
            $events,
            'shopping',
            'SAMPLESESSIONKEY',
            require(__DIR__ . '/helpers/configMock.php')
        );
    }

    public function tearDown(): void
    {
        m::close();
    }

    public function test_cart_can_add_item()
    {
        $this->cart->add(455, 'Sample Item', 100.99, 2, array());

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertEquals(1, $this->cart->getContent()->count(), 'Cart content should be 1');
        $this->assertEquals(455, $this->cart->getContent()->first()['id'], 'Item added has ID of 455 so first content ID should be 455');
        $this->assertEquals(100.99, $this->cart->getContent()->first()['price'], 'Item added has price of 100.99 so first content price should be 100.99');
    }

    public function test_cart_has_checks_item_presence()
    {
        $this->cart->add(455, 'Sample Item', 100.99, 2, array());

        $this->assertTrue($this->cart->has(455), 'Cart should contain item with id 455');
        $this->assertFalse($this->cart->has(999), 'Cart should not contain unknown item id');
    }

    public function test_cart_can_add_items_as_array()
    {
        $item = array(
            'id'         => 456,
            'name'       => 'Sample Item',
            'price'      => 67.99,
            'quantity'   => 4,
            'attributes' => array()
        );

        $this->cart->add($item);

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertEquals(1, $this->cart->getContent()->count(), 'Cart should have 1 item on it');
        $this->assertEquals(456, $this->cart->getContent()->first()['id'], 'The first content must have ID of 456');
        $this->assertEquals('Sample Item', $this->cart->getContent()->first()['name'], 'The first content must have name of "Sample Item"');
    }

    public function test_cart_can_add_items_with_multidimensional_array()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 4,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 4,
                'attributes' => array()
            ),
            array(
                'id'         => 856,
                'name'       => 'Sample Item 3',
                'price'      => 50.25,
                'quantity'   => 4,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertCount(3, $this->cart->getContent()->toArray(), 'Cart should have 3 items');
    }

    public function test_cart_can_add_many_items_with_single_batch_api()
    {
        $this->cart->addMany([
            [
                'id' => 456,
                'name' => 'Sample Item 1',
                'price' => 67.99,
                'quantity' => 4,
                'attributes' => [],
            ],
            [
                'id' => 568,
                'name' => 'Sample Item 2',
                'price' => 69.25,
                'quantity' => 1,
                'attributes' => [],
            ],
        ]);

        $this->assertCount(2, $this->cart->getContent());
        $this->assertEquals(5, $this->cart->getTotalQuantity());

        $this->cart->addMany([
            [
                'id' => 456,
                'name' => 'Sample Item 1',
                'price' => 67.99,
                'quantity' => 2,
                'attributes' => [],
            ],
        ]);

        $this->assertEquals(6, $this->cart->get(456)['quantity']);
    }

    public function test_cart_can_remove_many_items_with_single_batch_api()
    {
        $this->cart->addMany([
            ['id' => 456, 'name' => 'Sample Item 1', 'price' => 67.99, 'quantity' => 4],
            ['id' => 568, 'name' => 'Sample Item 2', 'price' => 69.25, 'quantity' => 1],
            ['id' => 856, 'name' => 'Sample Item 3', 'price' => 50.25, 'quantity' => 2],
        ]);

        $this->assertTrue($this->cart->removeMany([456, 856, 999]));
        $this->assertFalse($this->cart->has(456));
        $this->assertFalse($this->cart->has(856));
        $this->assertTrue($this->cart->has(568));
        $this->assertFalse($this->cart->removeMany([999]));
    }

    public function test_cart_can_use_configured_item_collection_class()
    {
        $config = require(__DIR__ . '/helpers/configMock.php');
        $config['item_collection_class'] = CustomItemCollection::class;

        $cart = new Cart(
            new SessionMock(),
            $this->events(),
            'shopping',
            'CUSTOMCOLLECTION',
            $config
        );

        $cart->add(456, 'Sample Item', 67.99, 1);

        $item = $cart->get(456);

        $this->assertInstanceOf(CustomItemCollection::class, $item);
        $this->assertEquals('custom-item-collection', $item->marker());
    }

    public function test_cart_can_add_item_without_attributes()
    {
        $item = array(
            'id'       => 456,
            'name'     => 'Sample Item 1',
            'price'    => 67.99,
            'quantity' => 4
        );

        $this->cart->add($item);

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
    }

    public function test_cart_update_with_attribute_then_attributes_should_be_still_instance_of_ItemAttributeCollection()
    {
        $item = array(
            'id'         => 456,
            'name'       => 'Sample Item 1',
            'price'      => 67.99,
            'quantity'   => 4,
            'attributes' => array(
                'product_id' => '145',
                'color'      => 'red'
            )
        );
        $this->cart->add($item);

        // lets get the attribute and prove first its an instance of
        // ItemAttributeCollection
        $item = $this->cart->get(456);

        $this->assertInstanceOf('Lalalili\ShoppingCart\ItemAttributeCollection', $item->attributes);

        // now lets update the item with its new attributes
        // when we get that item from cart, it should still be an instance of ItemAttributeCollection
        $updatedItem = array(
            'attributes' => array(
                'product_id' => '145',
                'color'      => 'red'
            )
        );
        $this->cart->update(456, $updatedItem);

        $this->assertInstanceOf('Lalalili\ShoppingCart\ItemAttributeCollection', $item->attributes);
    }

    public function test_cart_items_attributes()
    {
        $item = array(
            'id'         => 456,
            'name'       => 'Sample Item 1',
            'price'      => 67.99,
            'quantity'   => 4,
            'attributes' => array(
                'size'  => 'L',
                'color' => 'blue'
            )
        );

        $this->cart->add($item);

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertCount(2, $this->cart->getContent()->first()['attributes'], 'Item\'s attribute should have two');
        $this->assertEquals('L', $this->cart->getContent()->first()->attributes->size, 'Item should have attribute size of L');
        $this->assertEquals('blue', $this->cart->getContent()->first()->attributes->color, 'Item should have attribute color of blue');
        $this->assertTrue($this->cart->get(456)->has('attributes'), 'Item should have attributes');
        $this->assertEquals('L', $this->cart->get(456)->get('attributes')->size);
    }

    public function test_cart_update_existing_item()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $itemIdToEvaluate = 456;

        $item = $this->cart->get($itemIdToEvaluate);
        $this->assertEquals('Sample Item 1', $item['name'], 'Item name should be "Sample Item 1"');
        $this->assertEquals(67.99, $item['price'], 'Item price should be "67.99"');
        $this->assertEquals(3, $item['quantity'], 'Item quantity should be 3');

        // when cart's item quantity is updated, the subtotal should be updated as well
        $this->cart->update(456, array(
            'name'     => 'Renamed',
            'quantity' => 2,
            'price'    => 105,
        ));

        $item = $this->cart->get($itemIdToEvaluate);
        $this->assertEquals('Renamed', $item['name'], 'Item name should be "Renamed"');
        $this->assertEquals(105, $item['price'], 'Item price should be 105');
        $this->assertEquals(5, $item['quantity'], 'Item quantity should be 2');
    }

    public function test_cart_update_existing_item_with_quantity_as_array_and_not_relative()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $itemIdToEvaluate = 456;
        $item = $this->cart->get($itemIdToEvaluate);
        $this->assertEquals(3, $item['quantity'], 'Item quantity should be 3');

        // now by default when an update takes place and the quantity attribute
        // is present, it will evaluate for arithmetic operation if the quantity
        // should be incremented or decremented, we should also allow the quantity
        // value to be in array format and provide a field if the quantity should not be
        // treated as relative to Item quantity current value
        $this->cart->update($itemIdToEvaluate, array('quantity' => array('relative' => false, 'value' => 5)));

        $item = $this->cart->get($itemIdToEvaluate);
        $this->assertEquals(5, $item['quantity'], 'Item quantity should be 5');
    }

    public function test_item_price_should_be_normalized_when_added_to_cart()
    {
        // add a price in a string format should be converted to float
        $this->cart->add(455, 'Sample Item', '100.99', 2, array());

        $this->assertIsFloat($this->cart->getContent()->first()['price'], 'Cart price should be a float');
    }

    public function test_cart_rounds_item_price_before_multiplying_quantity_when_configured()
    {
        $config = require(__DIR__ . '/helpers/configMock.php');
        $config['rounding'] = [
            'item_price_before_quantity' => 0,
        ];

        $cart = new Cart(
            new SessionMock(),
            $this->events(),
            'shopping',
            'ROUNDING',
            $config
        );

        $cart->add(455, 'Sample Item', 100.49, 2, array());

        $this->assertEquals(200.0, $cart->getSubTotal(false));
    }

    public function test_cart_snapshot_contains_context_totals_hash_and_conditions()
    {
        $this->cart
            ->withContext(['customer_id' => 123, 'channel' => 'web'])
            ->add(455, 'Sample Item', 100, 2, ['color' => 'red']);

        $this->cart->condition(new CartCondition([
            'name' => 'shipping',
            'type' => 'shipping',
            'target' => 'total',
            'value' => '+50',
        ]));

        $snapshot = $this->cart->snapshot(false);

        $this->assertSame('shopping', $snapshot['instance']);
        $this->assertSame('SAMPLESESSIONKEY', $snapshot['session_key']);
        $this->assertSame(['customer_id' => 123, 'channel' => 'web'], $snapshot['context']);
        $this->assertSame(250.0, $snapshot['total']);
        $this->assertSame(2, $snapshot['quantity']);
        $this->assertNotEmpty($snapshot['hash']);
        $this->assertSame('shipping', $snapshot['conditions'][0]['name']);
        $this->assertSame('red', $snapshot['items'][0]['attributes']['color']);
    }

    public function test_cart_explains_item_subtotal_and_total_condition_steps()
    {
        $this->cart->add(455, 'Sample Item', 100, 2, [], [
            new CartCondition([
                'name' => 'line-discount',
                'type' => 'discount',
                'value' => '-10%',
            ]),
        ]);
        $this->cart->condition(new CartCondition([
            'name' => 'shipping',
            'type' => 'shipping',
            'target' => 'total',
            'value' => '+20',
        ]));

        $explain = $this->cart->explainTotals(false);

        $this->assertSame(180.0, $explain['subtotal']);
        $this->assertSame(200.0, $explain['subtotal_without_conditions']);
        $this->assertSame(200.0, $explain['total']);
        $this->assertSame('line-discount', $explain['items'][0]['condition_steps'][0]['condition']['name']);
        $this->assertSame('shipping', $explain['total_conditions'][0]['condition']['name']);
    }

    public function test_cart_hash_guard_detects_stale_cart_state()
    {
        $this->cart->add(455, 'Sample Item', 100, 1);
        $hash = $this->cart->hash();

        $this->cart->update(455, ['quantity' => 1]);

        $this->expectException(StaleCartException::class);

        $this->cart->assertHash($hash);
    }

    public function test_cart_runs_configured_before_totals_pipeline_once_per_state()
    {
        $config = require(__DIR__ . '/helpers/configMock.php');
        $config['pipelines'] = [
            'before_totals' => [AddDiscountPipeline::class],
        ];

        $cart = new Cart(
            new SessionMock(),
            $this->events(),
            'shopping',
            'PIPELINE',
            $config
        );

        $cart->withContext(['channel' => 'web']);
        $cart->add(455, 'Sample Item', 100, 1);

        $this->assertSame(90.0, $cart->getTotal(false));
        $this->assertSame(90.0, $cart->getTotal(false));
        $this->assertSame('web', $cart->getCondition('pipeline-discount')->getAttributes()['channel']);
        $this->assertTrue($cart->snapshot(false)['pipelines']['before_totals']['changed']);
    }

    public function test_cart_can_run_before_checkout_pipeline_and_expose_result()
    {
        $config = require(__DIR__ . '/helpers/configMock.php');
        $config['pipelines'] = [
            'before_checkout' => [WarningPipeline::class],
        ];

        $cart = new Cart(
            new SessionMock(),
            $this->events(),
            'shopping',
            'CHECKOUTPIPELINE',
            $config
        );

        $cart->withContext(['channel' => 'web']);
        $result = $cart->runPipelines('before_checkout');

        $this->assertFalse($result->changed());
        $this->assertSame(['checkout requires inventory confirmation'], $result->warnings());
        $this->assertSame('web', $cart->snapshot(false)['pipelines']['before_checkout']['metadata']['channel']);
    }

    public function test_it_removes_an_item_on_cart_by_item_id()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 4,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 4,
                'attributes' => array()
            ),
            array(
                'id'         => 856,
                'name'       => 'Sample Item 3',
                'price'      => 50.25,
                'quantity'   => 4,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $removeItemId = 456;

        $this->cart->remove($removeItemId);

        $this->assertCount(2, $this->cart->getContent()->toArray(), 'Cart must have 2 items left');
        $this->assertFalse($this->cart->getContent()->has($removeItemId), 'Cart must have not contain the remove item anymore');
    }

    public function test_cart_sub_total()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 1,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
            array(
                'id'         => 856,
                'name'       => 'Sample Item 3',
                'price'      => 50.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertEquals(187.49, $this->cart->getSubTotal(), 'Cart should have sub total of 187.49');

        // if we remove an item, the sub total should be updated as well
        $this->cart->remove(456);

        $this->assertEquals(119.5, $this->cart->getSubTotal(), 'Cart should have sub total of 119.5');
    }

    public function test_sub_total_when_item_quantity_is_updated()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertEqualsWithDelta(273.22, $this->cart->getSubTotal(), 0.00001, 'Cart should have sub total of 273.22');

        // when cart's item quantity is updated, the subtotal should be updated as well
        $this->cart->update(456, array('quantity' => 2));

        $this->assertEqualsWithDelta(409.2, $this->cart->getSubTotal(), 0.00001, 'Cart should have sub total of 409.2');
    }

    public function test_sub_total_when_item_quantity_is_updated_by_reduced()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertEqualsWithDelta(273.22, $this->cart->getSubTotal(), 0.00001, 'Cart should have sub total of 273.22');

        // when cart's item quantity is updated, the subtotal should be updated as well
        $this->cart->update(456, array('quantity' => -1));

        // get the item to be evaluated
        $item = $this->cart->get(456);

        $this->assertEquals(2, $item['quantity'], 'Item quantity of with item ID of 456 should now be reduced to 2');
        $this->assertEqualsWithDelta(205.23, $this->cart->getSubTotal(), 0.00001, 'Cart should have sub total of 205.23');
    }

    public function test_item_quantity_update_by_reduced_should_not_reduce_if_quantity_will_result_to_zero()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        // get the item to be evaluated
        $item = $this->cart->get(456);

        // prove first we have quantity of 3
        $this->assertEquals(3, $item['quantity'], 'Item quantity of with item ID of 456 should be reduced to 3');

        // when cart's item quantity is updated, and reduced to more than the current quantity
        // this should not work
        $this->cart->update(456, array('quantity' => -3));

        $this->assertEquals(3, $item['quantity'], 'Item quantity of with item ID of 456 should now be reduced to 2');
    }

    public function test_should_throw_exception_when_provided_invalid_values_scenario_one()
    {
        $this->expectException('Lalalili\ShoppingCart\Exceptions\InvalidItemException');
        $this->cart->add(455, 'Sample Item', 100.99, 0, array());
    }

    public function test_should_throw_exception_when_provided_invalid_values_scenario_two()
    {
        $this->expectException('Lalalili\ShoppingCart\Exceptions\InvalidItemException');
        $this->cart->add('', 'Sample Item', 100.99, 2, array());
    }

    public function test_should_throw_exception_when_provided_invalid_values_scenario_three()
    {
        $this->expectException('Lalalili\ShoppingCart\Exceptions\InvalidItemException');
        $this->cart->add(523, '', 100.99, 2, array());
    }

    public function test_clearing_cart()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertFalse($this->cart->isEmpty(), 'prove first cart is not empty');

        // now let's clear cart
        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty(), 'cart should now be empty');
    }

    public function test_cart_get_total_quantity()
    {
        $items = array(
            array(
                'id'         => 456,
                'name'       => 'Sample Item 1',
                'price'      => 67.99,
                'quantity'   => 3,
                'attributes' => array()
            ),
            array(
                'id'         => 568,
                'name'       => 'Sample Item 2',
                'price'      => 69.25,
                'quantity'   => 1,
                'attributes' => array()
            ),
        );

        $this->cart->add($items);

        $this->assertFalse($this->cart->isEmpty(), 'prove first cart is not empty');

        // now let's count the cart's quantity
        $this->assertIsInt($this->cart->getTotalQuantity(), 'Return type should be INT');
        $this->assertEquals(4, $this->cart->getTotalQuantity(), 'Cart\'s quantity should be 4.');
    }

    public function test_cart_can_add_items_as_array_with_associated_model()
    {
        $item = array(
            'id'              => 456,
            'name'            => 'Sample Item',
            'price'           => 67.99,
            'quantity'        => 4,
            'attributes'      => array(),
            'associatedModel' => MockProduct::class
        );

        $this->cart->add($item);

        $addedItem = $this->cart->get($item['id']);

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertEquals(1, $this->cart->getContent()->count(), 'Cart should have 1 item on it');
        $this->assertEquals(456, $this->cart->getContent()->first()['id'], 'The first content must have ID of 456');
        $this->assertEquals('Sample Item', $this->cart->getContent()->first()['name'], 'The first content must have name of "Sample Item"');
        $this->assertInstanceOf('Lalalili\ShoppingCart\Tests\Helpers\MockProduct', $addedItem->model);
    }

    public function test_cart_can_add_items_with_multidimensional_array_with_associated_model()
    {
        $items = array(
            array(
                'id'              => 456,
                'name'            => 'Sample Item 1',
                'price'           => 67.99,
                'quantity'        => 4,
                'attributes'      => array(),
                'associatedModel' => MockProduct::class
            ),
            array(
                'id'              => 568,
                'name'            => 'Sample Item 2',
                'price'           => 69.25,
                'quantity'        => 4,
                'attributes'      => array(),
                'associatedModel' => MockProduct::class
            ),
            array(
                'id'              => 856,
                'name'            => 'Sample Item 3',
                'price'           => 50.25,
                'quantity'        => 4,
                'attributes'      => array(),
                'associatedModel' => MockProduct::class
            ),
        );

        $this->cart->add($items);

        $content = $this->cart->getContent();
        foreach ($content as $item) {
            $this->assertInstanceOf('Lalalili\ShoppingCart\Tests\Helpers\MockProduct', $item->model);
        }

        $this->assertFalse($this->cart->isEmpty(), 'Cart should not be empty');
        $this->assertCount(3, $this->cart->getContent()->toArray(), 'Cart should have 3 items');
        $this->assertIsInt($this->cart->getTotalQuantity(), 'Return type should be INT');
        $this->assertEquals(12, $this->cart->getTotalQuantity(), 'Cart\'s quantity should be 4.');
    }

    private function events(): mixed
    {
        $events = m::mock('Illuminate\Contracts\Events\Dispatcher');
        $events->shouldReceive('dispatch');

        return $events;
    }
}
