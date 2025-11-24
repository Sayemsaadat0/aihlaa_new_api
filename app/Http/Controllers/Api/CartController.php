<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Discount;
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
                    'id' => null,
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
                            'size' => $price->size,
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

            // Calculate discount amount if discount code exists
            $discountAmount = 0;
            if (!empty($discountCoupon)) {
                $discount = Discount::where('code', $discountCoupon)
                    ->where('status', Discount::STATUS_PUBLISHED)
                    ->first();
                
                if ($discount) {
                    $discountAmount = (float) $discount->discount_price;
                    // Ensure discount doesn't exceed total (items_price + tax_price + delivery_charge)
                    $totalBeforeDiscount = $itemsPrice + $taxPrice + $deliveryCharge;
                    if ($discountAmount > $totalBeforeDiscount) {
                        $discountAmount = $totalBeforeDiscount;
                    }
                }
            }

            // Calculate payable price: items_price + tax + delivery - discount
            $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
            // Ensure payable price doesn't go below 0
            if ($payablePrice < 0) {
                $payablePrice = 0;
            }

            // Get first cart ID as the cart group identifier
            $firstCartId = $carts->first()->id ?? null;

            return $this->successResponse([
                'id' => $firstCartId,
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

            // Get existing cart items for user/guest
            $cartQuery = Cart::where(function ($query) use ($request) {
                if ($request->filled('user_id')) {
                    $query->where('user_id', $request->user_id)->whereNull('guest_id');
                } else {
                    $query->where('guest_id', $request->guest_id)->whereNull('user_id');
                }
            });
            $existingCarts = $cartQuery->get();

            // Validate items and prices
            $itemsPrice = 0;
            $validatedItems = [];
            $newCartsCreated = [];

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

                // Check if this item with same price already exists in cart
                $existingCartItem = $existingCarts->first(function ($cart) use ($itemId, $priceId) {
                    return $cart->item_id == $itemId && $cart->price_id == $priceId;
                });

                if ($existingCartItem) {
                    // Item already exists - increase quantity by adding more cart entries
                    for ($i = 0; $i < $quantity; $i++) {
                        $newCart = Cart::create([
                            'guest_id' => $request->guest_id,
                            'user_id' => $request->user_id,
                            'item_id' => $itemId,
                            'price_id' => $priceId,
                            'discount_coupon' => $existingCartItem->discount_coupon, // Preserve discount if exists
                            'payable_price' => $itemPriceValue,
                        ]);
                        $newCartsCreated[] = $newCart;
                    }
                } else {
                    // New item - create cart entries
                    for ($i = 0; $i < $quantity; $i++) {
                        $newCart = Cart::create([
                            'guest_id' => $request->guest_id,
                            'user_id' => $request->user_id,
                            'item_id' => $itemId,
                            'price_id' => $priceId,
                            'discount_coupon' => null,
                            'payable_price' => $itemPriceValue,
                        ]);
                        $newCartsCreated[] = $newCart;
                    }
                }

                $itemsPrice += $itemPriceValue * $quantity;
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

            // Get all cart items (existing + newly created) for the response
            $allCarts = $cartQuery->get();

            // Return summary similar to GET response (group items by item_id and price_id)
            $groupedItems = [];
            foreach ($allCarts as $cart) {
                $cart->load(['item', 'price']);
                if ($cart->item && $cart->price) {
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
            }

            // Build items array
            $items = [];
            $totalItemsPrice = 0;
            $discountCoupon = $allCarts->first()->discount_coupon ?? '';

            foreach ($groupedItems as $group) {
                $itemPrice = (float) $group['price']->price;
                $totalItemsPrice += $itemPrice * $group['quantity'];
                
                $items[] = [
                    'id' => $group['item']->id,
                    'title' => $group['item']->name,
                    'quantity' => $group['quantity'],
                    'price' => [
                        'id' => $group['price']->id,
                        'price' => $itemPrice,
                        'size' => $group['price']->size,
                    ],
                ];
            }

            // Recalculate totals based on all cart items
            $taxPrice = ($totalItemsPrice * $taxPercentage) / 100;

            // Calculate discount amount if discount code exists
            $discountAmount = 0;
            if (!empty($discountCoupon)) {
                $discount = Discount::where('code', $discountCoupon)
                    ->where('status', Discount::STATUS_PUBLISHED)
                    ->first();
                
                if ($discount) {
                    $discountAmount = (float) $discount->discount_price;
                    $totalBeforeDiscount = $totalItemsPrice + $taxPrice + $deliveryCharge;
                    if ($discountAmount > $totalBeforeDiscount) {
                        $discountAmount = $totalBeforeDiscount;
                    }
                }
            }

            $payablePrice = $totalItemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
            if ($payablePrice < 0) {
                $payablePrice = 0;
            }

            // Get first cart ID as the cart group identifier
            $firstCartId = $allCarts->first()->id ?? null;

            return $this->successResponse([
                'id' => $firstCartId,
                'guest_id' => $request->input('guest_id', ''),
                'user_id' => $request->input('user_id', ''),
                'items' => $items,
                'items_price' => round($totalItemsPrice, 2),
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
            ], 'Cart updated successfully', 200);
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
     * Update quantity for a specific item in cart (Public API)
     */
    public function updateQuantity(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
                'item_id' => 'required|integer|exists:items,id',
                'item_price_id' => 'required|integer|exists:item_prices,id',
                'quantity' => 'required|integer|min:0|max:999',
            ]);

            $userId = $validated['user_id'] ?? null;
            $guestId = $validated['guest_id'] ?? null;
            $itemId = $validated['item_id'];
            $priceId = $validated['item_price_id'];
            $newQuantity = (int) $validated['quantity'];

            // Validate that user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    return $this->errorResponse(
                        'User does not exist',
                        404
                    );
                }
            }

            // Validate that item exists
            $item = Item::find($itemId);
            if (!$item) {
                return $this->errorResponse(
                    "Item with ID {$itemId} does not exist",
                    404
                );
            }

            // Validate that price exists and belongs to the item
            $price = ItemPrice::where('id', $priceId)
                ->where('item_id', $itemId)
                ->first();

            if (!$price) {
                return $this->errorResponse(
                    "Price with ID {$priceId} does not belong to item with ID {$itemId}",
                    422
                );
            }

            // Build query to find existing cart entries for this specific item and price
            $cartQuery = Cart::where('item_id', $itemId)
                ->where('price_id', $priceId);

            if ($userId) {
                $cartQuery->where('user_id', $userId)
                    ->whereNull('guest_id');
            } else {
                $cartQuery->where('guest_id', $guestId)
                    ->whereNull('user_id');
            }

            $existingCarts = $cartQuery->get();
            $currentQuantity = $existingCarts->count();

            // If quantity is 0, remove all cart entries for this item
            if ($newQuantity === 0) {
                if ($existingCarts->isEmpty()) {
                    return $this->errorResponse(
                        'Item not found in cart',
                        404
                    );
                }

                // Delete all cart entries
                $cartQuery->delete();

                // Return updated cart summary
                return $this->getUpdatedCartSummary($userId, $guestId, 'Item quantity updated successfully (item removed from cart)');
            }

            // If item doesn't exist in cart and quantity > 0, add it
            if ($existingCarts->isEmpty()) {
                // Create new cart entries
                $priceValue = (float) $price->price;
                for ($i = 0; $i < $newQuantity; $i++) {
                    Cart::create([
                        'guest_id' => $guestId,
                        'user_id' => $userId,
                        'item_id' => $itemId,
                        'price_id' => $priceId,
                        'discount_coupon' => null,
                        'payable_price' => $priceValue,
                    ]);
                }

                // Return updated cart summary
                return $this->getUpdatedCartSummary($userId, $guestId, 'Item added to cart successfully');
            }

            // Item exists in cart, update quantity
            if ($newQuantity > $currentQuantity) {
                // Need to add more entries
                $priceValue = (float) $price->price;
                $entriesToAdd = $newQuantity - $currentQuantity;
                for ($i = 0; $i < $entriesToAdd; $i++) {
                    Cart::create([
                        'guest_id' => $guestId,
                        'user_id' => $userId,
                        'item_id' => $itemId,
                        'price_id' => $priceId,
                        'discount_coupon' => null,
                        'payable_price' => $priceValue,
                    ]);
                }
            } elseif ($newQuantity < $currentQuantity) {
                // Need to remove some entries
                $entriesToRemove = $currentQuantity - $newQuantity;
                $cartsToDelete = $existingCarts->take($entriesToRemove);
                foreach ($cartsToDelete as $cart) {
                    $cart->delete();
                }
            }
            // If newQuantity === currentQuantity, no changes needed

            // Return updated cart summary
            return $this->getUpdatedCartSummary($userId, $guestId, 'Item quantity updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update cart item quantity: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete an item completely from cart (Public API)
     */
    public function deleteItem(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
                'item_id' => 'required|integer|exists:items,id',
                'item_price_id' => 'required|integer|exists:item_prices,id',
            ]);

            $userId = $validated['user_id'] ?? null;
            $guestId = $validated['guest_id'] ?? null;
            $itemId = $validated['item_id'];
            $priceId = $validated['item_price_id'];

            // Validate that user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    return $this->errorResponse(
                        'User does not exist',
                        404
                    );
                }
            }

            // Validate that item exists
            $item = Item::find($itemId);
            if (!$item) {
                return $this->errorResponse(
                    "Item with ID {$itemId} does not exist",
                    404
                );
            }

            // Validate that price exists and belongs to the item
            $price = ItemPrice::where('id', $priceId)
                ->where('item_id', $itemId)
                ->first();

            if (!$price) {
                return $this->errorResponse(
                    "Price with ID {$priceId} does not belong to item with ID {$itemId}",
                    422
                );
            }

            // Build query to find existing cart entries for this item and price
            $cartQuery = Cart::where('item_id', $itemId)
                ->where('price_id', $priceId);

            if ($userId) {
                $cartQuery->where('user_id', $userId)
                    ->whereNull('guest_id');
            } else {
                $cartQuery->where('guest_id', $guestId)
                    ->whereNull('user_id');
            }
            $existingCarts = $cartQuery->get();

            if ($existingCarts->isEmpty()) {
                return $this->errorResponse(
                    'Item not found in cart',
                    404
                );
            }

            // Delete all cart entries for this item and price
            $deletedCount = $cartQuery->delete();

            // Return updated cart summary
            return $this->getUpdatedCartSummary($userId, $guestId, 'Item removed from cart successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete item from cart: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Apply discount code to cart (Public API)
     */
    public function applyDiscount(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
                'code' => 'required|string|max:255',
            ]);

            $userId = $validated['user_id'] ?? null;
            $guestId = $validated['guest_id'] ?? null;
            $code = trim($validated['code']);

            // Validate that user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    return $this->errorResponse(
                        'User does not exist',
                        404
                    );
                }
            }

            // Find discount by code
            $discount = Discount::where('code', $code)
                ->where('status', Discount::STATUS_PUBLISHED)
                ->first();

            if (!$discount) {
                return $this->errorResponse(
                    'Invalid or inactive discount code',
                    404
                );
            }

            // Build query to find cart entries
            $cartQuery = Cart::where(function ($query) use ($userId, $guestId) {
                if ($userId) {
                    $query->where('user_id', $userId)
                        ->whereNull('guest_id');
                } else {
                    $query->where('guest_id', $guestId)
                        ->whereNull('user_id');
                }
            });

            $carts = $cartQuery->get();

            if ($carts->isEmpty()) {
                return $this->errorResponse(
                    'Cart is empty. Please add items to cart first.',
                    404
                );
            }

            // Update all cart entries with the discount coupon
            $cartQuery->update([
                'discount_coupon' => $code,
            ]);

            // Return updated cart summary with discount applied
            return $this->getUpdatedCartSummary($userId, $guestId, 'Discount code applied successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to apply discount code: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete entire cart (Public API)
     */
    public function deleteCart(Request $request, $id)
    {
        try {
            // Validate cart ID
            if (!is_numeric($id)) {
                return $this->errorResponse(
                    'Invalid cart ID. Cart ID must be a number.',
                    422
                );
            }

            $cartId = (int) $id;

            // Find the cart entry
            $cart = Cart::find($cartId);
            if (!$cart) {
                return $this->errorResponse(
                    'Cart with ID ' . $cartId . ' not found.',
                    404
                );
            }

            // Get user_id or guest_id from the cart
            $userId = $cart->user_id;
            $guestId = $cart->guest_id;

            // Build query to find all cart entries for this user/guest
            $cartQuery = Cart::where(function ($query) use ($userId, $guestId) {
                if ($userId) {
                    $query->where('user_id', $userId)->whereNull('guest_id');
                } else {
                    $query->where('guest_id', $guestId)->whereNull('user_id');
                }
            });

            // Get count before deletion
            $deletedCount = $cartQuery->count();

            if ($deletedCount === 0) {
                return $this->errorResponse(
                    'Cart is already empty.',
                    404
                );
            }

            // Delete all cart entries
            $cartQuery->delete();

            return $this->successResponse([
                'deleted_count' => $deletedCount,
            ], 'Cart deleted successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete cart: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove discount from cart (Public API)
     */
    public function removeDiscount(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
            ]);

            $userId = $validated['user_id'] ?? null;
            $guestId = $validated['guest_id'] ?? null;

            // Validate that user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    return $this->errorResponse(
                        'User does not exist',
                        404
                    );
                }
            }

            // Build query to find cart entries
            $cartQuery = Cart::where(function ($query) use ($userId, $guestId) {
                if ($userId) {
                    $query->where('user_id', $userId)
                        ->whereNull('guest_id');
                } else {
                    $query->where('guest_id', $guestId)
                        ->whereNull('user_id');
                }
            });

            $carts = $cartQuery->get();

            if ($carts->isEmpty()) {
                return $this->errorResponse(
                    'Cart is empty',
                    404
                );
            }

            // Remove discount coupon from all cart entries
            $cartQuery->update([
                'discount_coupon' => null,
            ]);

            // Return updated cart summary without discount
            return $this->getUpdatedCartSummary($userId, $guestId, 'Discount code removed successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to remove discount code: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get updated cart summary after quantity update
     * 
     * @param int|null $userId
     * @param string|null $guestId
     * @param string $message
     * @return JsonResponse
     */
    private function getUpdatedCartSummary($userId, $guestId, $message)
    {
        try {
            $query = Cart::with(['item', 'price']);

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($guestId) {
                $query->where('guest_id', $guestId);
            }

            $carts = $query->get();

            if ($carts->isEmpty()) {
                // Get restaurant data for tax percentage
                $restaurant = Restaurant::first();
                $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
                $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;
                
                return $this->successResponse([
                    'id' => null,
                    'guest_id' => $guestId ?? '',
                    'user_id' => $userId ?? '',
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
                ], $message);
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
                            'size' => $price->size,
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

            // Calculate discount amount if discount code exists
            $discountAmount = 0;
            if (!empty($discountCoupon)) {
                $discount = Discount::where('code', $discountCoupon)
                    ->where('status', Discount::STATUS_PUBLISHED)
                    ->first();
                
                if ($discount) {
                    $discountAmount = (float) $discount->discount_price;
                    // Ensure discount doesn't exceed total (items_price + tax_price + delivery_charge)
                    $totalBeforeDiscount = $itemsPrice + $taxPrice + $deliveryCharge;
                    if ($discountAmount > $totalBeforeDiscount) {
                        $discountAmount = $totalBeforeDiscount;
                    }
                }
            }

            // Calculate payable price: items_price + tax + delivery - discount
            $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
            // Ensure payable price doesn't go below 0
            if ($payablePrice < 0) {
                $payablePrice = 0;
            }

            // Get first cart ID as the cart group identifier
            $firstCartId = $carts->first()->id ?? null;

            return $this->successResponse([
                'id' => $firstCartId,
                'guest_id' => $guestId ?? '',
                'user_id' => $userId ?? '',
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
            ], $message);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve updated cart: ' . $e->getMessage(),
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
                        'id' => $cart->id, // First cart ID as group identifier
                        'cart_ids' => [], // All cart IDs in this group
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

                // Add cart ID to the group
                $groupedCarts[$key]['cart_ids'][] = $cart->id;

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
                            'size' => $price->size,
                        ],
                    ];
                }

                // Calculate discount amount if discount code exists
                $discountAmount = 0;
                if (!empty($group['discount']['coupon'])) {
                    $discount = Discount::where('code', $group['discount']['coupon'])
                        ->where('status', Discount::STATUS_PUBLISHED)
                        ->first();
                    
                    if ($discount) {
                        $discountAmount = (float) $discount->discount_price;
                        $totalBeforeDiscount = $itemsPrice + ($itemsPrice * $taxPercentage / 100) + $deliveryCharge;
                        if ($discountAmount > $totalBeforeDiscount) {
                            $discountAmount = $totalBeforeDiscount;
                        }
                    }
                }

                $taxPrice = ($itemsPrice * $taxPercentage) / 100;
                $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
                if ($payablePrice < 0) {
                    $payablePrice = 0;
                }

                $formattedCarts[] = [
                    'id' => $group['id'],
                    'guest_id' => $group['guest_id'],
                    'user_id' => $group['user_id'],
                    'user' => $group['user'],
                    'items' => $items,
                    'items_price' => round($itemsPrice, 2),
                    'discount' => [
                        'coupon' => $group['discount']['coupon'],
                        'amount' => round($discountAmount, 2),
                    ],
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
