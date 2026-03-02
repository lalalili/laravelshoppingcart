<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Validators;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

abstract class Validator
{
    protected static ?Factory $factory = null;

    public static function setFactory(Factory $factory): void
    {
        static::$factory = $factory;
    }

    protected static function instance(): Factory
    {
        if (static::$factory instanceof Factory) {
            return static::$factory;
        }

        if (function_exists('app') && app()->bound('validator')) {
            /** @var mixed $validatorFactory */
            $validatorFactory = app('validator');
            if ($validatorFactory instanceof Factory) {
                static::$factory = $validatorFactory;

                return static::$factory;
            }
        }

        $translator = new Translator(new ArrayLoader(), 'en');
        static::$factory = new Factory($translator);

        return static::$factory;
    }

    public static function make(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): \Illuminate\Contracts\Validation\Validator {
        return static::instance()->make($data, $rules, $messages, $customAttributes);
    }
}
