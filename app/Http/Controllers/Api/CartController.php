<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * Get cart summary (Public API)
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
            ]);

            $query = Cart::with(['item', 'price']);

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('guest_id')) {
                $query->where('guest_id', $request->guest_id);
            }

            $carts = $query->get();

            if ($carts->isEmpty()) {
                // Get restaurant data for tax percentage
                $restaurant = Restaurant::first();
                $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
                $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;
                
                return $this->successResponse([
                    'guest_id' => $request->input('guest_id', ''),
                    'user_id' => $request->input('user_id', ''),
                    'items' => [],
                    'items_price' => 0,
                    'discount' => [
                        'coupon' => '',
                        'amount' => 0,
                    ],
                    'charges' => [
                        'tax' => round($taxPercentage, 2),
                        'tax_price' => 0,
                        'delivery_charges' => round($deliveryCharge, 2),
                    ],
                    'payable_price' => 0,
                ], 'Cart retrieved successfully');
            }

            // Group items by item_id and price_id to calculate quantity
            $groupedItems = [];
            foreach ($carts as $cart) {
                $key = $cart->item_id . '_' . $cart->price_id;
                if (!isset($groupedItems[$key])) {
                    $groupedItems[$key] = [
                        'item' => $cart->item,
                        'price' => $cart->price,
                        'quantity' => 0,
                        'cart_ids' => [],
                    ];
                }
                $groupedItems[$key]['quantity']++;
                $groupedItems[$key]['cart_ids'][] = $cart->id;
            }

            // Build items array
            $items = [];
            $itemsPrice = 0;
            $discountCoupon = $carts->first()->discount_coupon ?? '';

            foreach ($groupedItems as $group) {
                $item = $group['item'];
                $price = $group['price'];
                
                if ($item && $price) {
                    $itemPrice = (float) $price->price;
                    $itemsPrice += $itemPrice * $group['quantity'];
                    
                    $items[] = [
                        'id' => $item->id,
                        'title' => $item->name,
                        'quantity' => $group['quantity'],
                        'price' => [
                            'id' => $price->id,
                            'price' => $itemPrice,
                        ],
                    ];
                }
            }

            // Get restaurant data for tax and delivery_charge
            $restaurant = Restaurant::first();
            $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
            $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;

            // Calculate tax price (percentage of items_price)
            $taxPrice = ($itemsPrice * $taxPercentage) / 100;

            // Calculate payable price
            $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge;

            // For now, discount amount is 0 (will be calculated later when discount API is integrated)
            $discountAmount = 0;

            return $this->successResponse([
                'guest_id' => $request->input('guest_id', ''),
                'user_id' => $request->input('user_id', ''),
                'items' => $items,
                'items_price' => round($itemsPrice, 2),
                'discount' => [
                    'coupon' => $discountCoupon,
                    'amount' => round($discountAmount, 2),
                ],
                'charges' => [
                    'tax' => round($taxPercentage, 2),
                    'tax_price' => round($taxPrice, 2),
                    'delivery_charges' => round($deliveryCharge, 2),
                ],
                'payable_price' => round($payablePrice, 2),
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
     * Create cart entries (Public API)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
                'items' => 'required|array|min:1',
                'items.*.item_id' => 'required|integer|exists:items,id',
                'items.*.item_price_id' => 'required|integer|exists:item_prices,id',
                'items.*.quantity' => 'nullable|integer|min:1',
            ]);

            // Validate items and prices
            $itemsPrice = 0;
            $validatedItems = [];

            foreach ($request->items as $index => $item) {
                $itemId = $item['item_id'];
                $priceId = $item['item_price_id'];
                $quantity = isset($item['quantity']) && $item['quantity'] > 0 ? (int) $item['quantity'] : 1;

                // Check if item exists
                $itemModel = Item::find($itemId);
                if (!$itemModel) {
                    return $this->errorResponse(
                        "Item with ID {$itemId} does not exist (at index {$index})",
                        404
                    );
                }

                // Check if price exists and belongs to the item
                $price = ItemPrice::where('id', $priceId)
                    ->where('item_id', $itemId)
                    ->first();

                if (!$price) {
                    return $this->errorResponse(
                        "Price with ID {$priceId} does not belong to item with ID {$itemId} (at index {$index})",
                        422
                    );
                }

                $itemPriceValue = (float) $price->price;
                $itemsPrice += $itemPriceValue * $quantity;
                $validatedItems[] = [
                    'item_id' => $itemId,
                    'price_id' => $priceId,
                    'price_value' => $itemPriceValue,
                    'quantity' => $quantity,
                ];
            }

            // Get restaurant data for tax and delivery_charge
            $restaurant = Restaurant::first();
            if (!$restaurant) {
                return $this->errorResponse(
                    'Restaurant configuration not found. Please configure restaurant settings first.',
                    404
                );
            }

            $taxPercentage = (float) $restaurant->tax;
            $deliveryCharge = (float) $restaurant->delivery_charge;

            // Calculate tax price (percentage of items_price)
            $taxPrice = ($itemsPrice * $taxPercentage) / 100;

            // Calculate payable price
            $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge;

            // Create cart entries for each item (create multiple entries based on quantity)
            $createdCarts = [];
            foreach ($validatedItems as $validatedItem) {
                $quantity = $validatedItem['quantity'];
                // Create one cart entry for each quantity
                for ($i = 0; $i < $quantity; $i++) {
                    $cart = Cart::create([
                        'guest_id' => $request->guest_id,
                        'user_id' => $request->user_id,
                        'item_id' => $validatedItem['item_id'],
                        'price_id' => $validatedItem['price_id'],
                        'discount_coupon' => null,
                        'payable_price' => $validatedItem['price_value'], // Store individual item price
                    ]);
                    $createdCarts[] = $cart;
                }
            }

            // Return summary similar to GET response (group items by item_id and price_id)
            $groupedItems = [];
            foreach ($createdCarts as $cart) {
                $cart->load(['item', 'price']);
                if ($cart->item && $cart->price) {
                    $key = $cart->item_id . '_' . $cart->price_id;
                    if (!isset($groupedItems[$key])) {
                        $groupedItems[$key] = [
                            'item' => $cart->item,
                            'price' => $cart->price,
                            'quantity' => 0,
                        ];
                    }
                    $groupedItems[$key]['quantity']++;
                }
            }

            $items = [];
            foreach ($groupedItems as $group) {
                $items[] = [
                    'id' => $group['item']->id,
                    'title' => $group['item']->name,
                    'quantity' => $group['quantity'],
                    'price' => [
                        'id' => $group['price']->id,
                        'price' => (float) $group['price']->price,
                    ],
                ];
            }

            return $this->successResponse([
                'guest_id' => $request->input('guest_id', ''),
                'user_id' => $request->input('user_id', ''),
                'items' => $items,
                'items_price' => round($itemsPrice, 2),
                'discount' => [
                    'coupon' => '',
                    'amount' => 0, // Will be calculated when discount API is integrated
                ],
                'charges' => [
                    'tax' => round($taxPercentage, 2),
                    'tax_price' => round($taxPrice, 2),
                    'delivery_charges' => round($deliveryCharge, 2),
                ],
                'payable_price' => round($payablePrice, 2),
            ], 'Cart created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create cart: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get all carts (Admin only)
     */
    public function getAllCarts(Request $request)
    {
        try {
            $carts = Cart::with(['item', 'price', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Group carts by guest_id or user_id
            $groupedCarts = [];
            foreach ($carts as $cart) {
                $key = $cart->user_id ? 'user_' . $cart->user_id : 'guest_' . $cart->guest_id;
                
                if (!isset($groupedCarts[$key])) {
                    $groupedCarts[$key] = [
                        'guest_id' => $cart->guest_id,
                        'user_id' => $cart->user_id,
                        'user' => $cart->user ? [
                            'id' => $cart->user->id,
                            'name' => $cart->user->name,
                            'email' => $cart->user->email,
                        ] : null,
                        'items' => [],
                        'items_price' => 0,
                        'discount' => [
                            'coupon' => $cart->discount_coupon ?? '',
                            'amount' => 0,
                        ],
                        'charges' => [
                            'tax' => 0,
                            'tax_price' => 0,
                            'delivery_charges' => 0,
                        ],
                        'payable_price' => 0,
                        'created_at' => $cart->created_at,
                        'updated_at' => $cart->updated_at,
                    ];
                }

                // Add item to the group
                if ($cart->item && $cart->price) {
                    $itemKey = $cart->item_id . '_' . $cart->price_id;
                    if (!isset($groupedCarts[$key]['items'][$itemKey])) {
                        $groupedCarts[$key]['items'][$itemKey] = [
                            'item' => $cart->item,
                            'price' => $cart->price,
                            'quantity' => 0,
                        ];
                    }
                    $groupedCarts[$key]['items'][$itemKey]['quantity']++;
                }
            }

            // Format the response
            $formattedCarts = [];
            $restaurant = Restaurant::first();
            $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
            $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;

            foreach ($groupedCarts as $group) {
                $items = [];
                $itemsPrice = 0;

                foreach ($group['items'] as $itemGroup) {
                    $item = $itemGroup['item'];
                    $price = $itemGroup['price'];
                    $quantity = $itemGroup['quantity'];
                    $itemPrice = (float) $price->price;
                    $itemsPrice += $itemPrice * $quantity;

                    $items[] = [
                        'id' => $item->id,
                        'title' => $item->name,
                        'quantity' => $quantity,
                        'price' => [
                            'id' => $price->id,
                            'price' => $itemPrice,
                        ],
                    ];
                }

                $taxPrice = ($itemsPrice * $taxPercentage) / 100;
                $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge;

                $formattedCarts[] = [
                    'guest_id' => $group['guest_id'],
                    'user_id' => $group['user_id'],
                    'user' => $group['user'],
                    'items' => $items,
                    'items_price' => round($itemsPrice, 2),
                    'discount' => $group['discount'],
                    'charges' => [
                        'tax' => round($taxPercentage, 2),
                        'tax_price' => round($taxPrice, 2),
                        'delivery_charges' => round($deliveryCharge, 2),
                    ],
                    'payable_price' => round($payablePrice, 2),
                    'created_at' => $group['created_at'],
                    'updated_at' => $group['updated_at'],
                ];
            }

            return $this->successResponse([
                'carts' => $formattedCarts,
                'total' => count($formattedCarts),
            ], 'All carts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve all carts: ' . $e->getMessage(),
                500
            );
        }
    }
}
