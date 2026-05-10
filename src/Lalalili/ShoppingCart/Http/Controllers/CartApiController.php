<?php

declare(strict_types=1);

namespace Lalalili\ShoppingCart\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lalalili\ShoppingCart\Cart;
use Lalalili\ShoppingCart\CartCondition;
use Lalalili\ShoppingCart\Exceptions\StaleCartException;

class CartApiController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->cart()->snapshot($request->boolean('formatted')));
    }

    public function add(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->add($request->validate($this->itemRules()));

        return $this->show($request);
    }

    public function addMany(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.1'],
            'items.*.attributes' => ['sometimes', 'array'],
        ]);

        $items = $validated['items'] ?? [];
        $this->cart()->addMany(is_array($items) ? $items : []);

        return $this->show($request);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->update($id, $request->validate([
            'name' => ['sometimes', 'string'],
            'price' => ['sometimes', 'numeric'],
            'quantity' => ['sometimes'],
            'attributes' => ['sometimes', 'array'],
        ]));

        return $this->show($request);
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->remove($id);

        return $this->show($request);
    }

    public function removeMany(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required'],
        ]);

        $ids = $validated['ids'] ?? [];
        $this->cart()->removeMany(is_array($ids) ? $ids : []);

        return $this->show($request);
    }

    public function condition(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->condition(new CartCondition($request->validate([
            'name' => ['required', 'string'],
            'type' => ['required', 'string'],
            'value' => ['required'],
            'target' => ['sometimes', 'string'],
            'order' => ['sometimes', 'integer'],
            'attributes' => ['sometimes', 'array'],
        ])));

        return $this->show($request);
    }

    public function context(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->withContext($request->except(['cart_hash', 'hash']));

        return $this->show($request);
    }

    public function clear(Request $request): JsonResponse
    {
        if ($response = $this->assertHashPrecondition($request)) {
            return $response;
        }

        $this->cart()->clear();
        $this->cart()->clearCartConditions();

        return $this->show($request);
    }

    private function cart(): Cart
    {
        /** @var Cart $cart */
        $cart = app('shopping_cart');

        return $cart;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function itemRules(): array
    {
        return [
            'id' => ['required'],
            'name' => ['required', 'string'],
            'price' => ['required', 'numeric'],
            'quantity' => ['required', 'numeric', 'min:0.1'],
            'attributes' => ['sometimes', 'array'],
        ];
    }

    private function assertHashPrecondition(Request $request): ?JsonResponse
    {
        $expectedHash = $this->expectedHash($request);

        if ($expectedHash === null) {
            if ((bool) config('shopping_cart.api.require_hash', false)) {
                return response()->json([
                    'message' => 'Cart hash is required for this operation.',
                    'current_hash' => $this->cart()->hash(),
                ], 428);
            }

            return null;
        }

        try {
            $this->cart()->assertHash($expectedHash);
        } catch (StaleCartException) {
            return response()->json([
                'message' => 'The cart has changed since the supplied hash was generated.',
                'current_hash' => $this->cart()->hash(),
            ], 409);
        }

        return null;
    }

    private function expectedHash(Request $request): ?string
    {
        $hash = $request->headers->get('If-Match')
            ?? $request->headers->get('X-Cart-Hash')
            ?? $request->input('cart_hash')
            ?? $request->input('hash');

        if (!is_string($hash) || trim($hash) === '') {
            return null;
        }

        return trim($hash, "\" \t\n\r\0\x0B");
    }
}
