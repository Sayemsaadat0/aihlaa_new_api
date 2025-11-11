<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Get cart entries for a guest or user (Public API)
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|exists:users,id|required_without:guest_id',
            ]);

            $query = Cart::with(['item', 'price']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('guest_id')) {
                $query->where('guest_id', $request->guest_id);
            }

            $carts = $query->get();

            $cartData = $carts->map(function (Cart $cart) {
                return [
                    'id' => $cart->id,
                    'guest_id' => $cart->guest_id,
                    'user_id' => $cart->user_id,
                    'item' => $cart->item ? [
                        'id' => $cart->item->id,
                        'name' => $cart->item->name,
                    ] : null,
                    'price' => $cart->price ? [
                        'id' => $cart->price->id,
                        'price' => (float) $cart->price->price,
                    ] : null,
                    'discount_coupon' => $cart->discount_coupon,
                    'payable_price' => (float) $cart->payable_price,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                ];
            });

            return $this->successResponse([
                'carts' => $cartData,
                'total' => $cartData->count(),
            ], 'Cart retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve cart: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a cart entry (Public API)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|exists:users,id|required_without:guest_id',
                'item_id' => 'required|exists:items,id',
                'price_id' => 'required|exists:item_prices,id',
                'discount_coupon' => 'nullable|string|max:255',
                'payable_price' => 'required|numeric|min:0|max:99999999.99',
            ]);

            // Ensure the selected price belongs to the given item
            $price = ItemPrice::where('id', $request->price_id)
                ->where('item_id', $request->item_id)
                ->first();

            if (!$price) {
                return $this->errorResponse(
                    'The selected price does not belong to the specified item.',
                    422
                );
            }

            $cart = Cart::create([
                'guest_id' => $request->guest_id,
                'user_id' => $request->user_id,
                'item_id' => $request->item_id,
                'price_id' => $request->price_id,
                'discount_coupon' => $request->discount_coupon,
                'payable_price' => $request->payable_price,
            ]);

            $cart->load(['item', 'price']);

            return $this->successResponse([
                'cart' => [
                    'id' => $cart->id,
                    'guest_id' => $cart->guest_id,
                    'user_id' => $cart->user_id,
                    'item' => $cart->item ? [
                        'id' => $cart->item->id,
                        'name' => $cart->item->name,
                    ] : null,
                    'price' => $cart->price ? [
                        'id' => $cart->price->id,
                        'price' => (float) $cart->price->price,
                    ] : null,
                    'discount_coupon' => $cart->discount_coupon,
                    'payable_price' => (float) $cart->payable_price,
                    'created_at' => $cart->created_at,
                    'updated_at' => $cart->updated_at,
                ],
            ], 'Cart entry created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create cart entry: ' . $e->getMessage(),
                500
            );
        }
    }
}
