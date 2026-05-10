<?php

return [
    /*
     * ---------------------------------------------------------------
     * formatting
     * ---------------------------------------------------------------
     *
     * the formatting of shopping cart values
     */
    'format_numbers' => env('SHOPPING_CART_FORMAT_VALUES', false),

    'decimals' => env('SHOPPING_CART_DECIMALS', 0),

    'dec_point' => env('SHOPPING_CART_DEC_POINT', '.'),

    'thousands_sep' => env('SHOPPING_CART_THOUSANDS_SEP', ','),

    /*
     * ---------------------------------------------------------------
     * persistence
     * ---------------------------------------------------------------
     *
     * the configuration for persisting cart
     */
    'storage' => null,

    /*
     * ---------------------------------------------------------------
     * events
     * ---------------------------------------------------------------
     *
     * the configuration for cart events
     */
    'events' => null,

    /*
     * ---------------------------------------------------------------
     * item collection extension
     * ---------------------------------------------------------------
     */
    'item_collection_class' => null,

    /*
     * ---------------------------------------------------------------
     * associated model resolution
     * ---------------------------------------------------------------
     */
    'associated_model_resolver' => null,

    /*
     * ---------------------------------------------------------------
     * rounding
     * ---------------------------------------------------------------
     *
     * Each rule may be null, an integer precision, or:
     * ['precision' => 0, 'mode' => 'half_up']
     */
    'rounding' => [
        'item_price' => null,
        'item_price_before_quantity' => null,
        'line_subtotal' => null,
        'subtotal_without_conditions' => null,
        'subtotal' => null,
        'total' => null,
    ],

    /*
     * ---------------------------------------------------------------
     * checkout context
     * ---------------------------------------------------------------
     */
    'context' => [
        'defaults' => [],
    ],

    /*
     * ---------------------------------------------------------------
     * cart pipelines
     * ---------------------------------------------------------------
     *
     * Each stage may contain callables or classes implementing
     * CartPipelineInterface. before_totals is run automatically.
     */
    'pipelines' => [
        'auto_run_before_totals' => true,
        'before_totals' => [],
        'after_totals' => [],
        'before_checkout' => [],
    ],

    /*
     * ---------------------------------------------------------------
     * optional store API
     * ---------------------------------------------------------------
     */
    'api' => [
        'enabled' => false,
        'require_hash' => false,
        'prefix' => 'cart',
        'middleware' => [],
    ],
];
