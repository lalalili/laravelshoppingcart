<?php

return [
    /*
     * ---------------------------------------------------------------
     * formatting
     * ---------------------------------------------------------------
     *
     * the formatting of shopping cart values
     */
    'format_numbers' => env('LALALILI_SHOPPING_FORMAT_VALUES', env('SHOPPING_FORMAT_VALUES', false)),

    'decimals' => env('LALALILI_SHOPPING_DECIMALS', env('SHOPPING_DECIMALS', 0)),

    'dec_point' => env('LALALILI_SHOPPING_DEC_POINT', env('SHOPPING_DEC_POINT', '.')),

    'thousands_sep' => env('LALALILI_SHOPPING_THOUSANDS_SEP', env('SHOPPING_THOUSANDS_SEP', ',')),

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
];
