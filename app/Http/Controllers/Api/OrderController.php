<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Restaurant;
use App\Models\Discount;
use App\Models\User;
use App\Models\Item;
use App\Models\ItemPrice;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class OrderController extends Controller
{
    /**
     * Create a new order (Public API)
     */
    public function store(Request $request)
    {
        try {
            // Validate request with custom error messages
            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id|required_without:guest_id',
                'guest_id' => 'nullable|string|max:255|required_without:user_id',
                'cart_id' => 'nullable|integer|exists:carts,id',
                // Address validation - either address_id or full address
                'address_id' => 'nullable|integer|exists:addresses,id',
                'city_id' => 'nullable|integer|exists:cities,id|required_without:address_id',
                'state' => 'nullable|string|max:255|required_without:address_id',
                'zip_code' => 'nullable|string|max:50|required_without:address_id',
                'street_address' => 'nullable|string|required_without:address_id',
                'phone' => 'required|string|max:255',
                'email' => 'nullable|string|email|max:255',
                'notes' => 'nullable|string',
            ], [
                'user_id.required_without' => 'Either user_id or guest_id is required.',
                'user_id.exists' => 'The selected user does not exist.',
                'guest_id.required_without' => 'Either user_id or guest_id is required.',
                'guest_id.max' => 'Guest ID cannot exceed 255 characters.',
                'cart_id.exists' => 'The selected cart does not exist.',
                'address_id.exists' => 'The selected address does not exist.',
                'city_id.required_without' => 'City ID is required when address_id is not provided.',
                'city_id.exists' => 'The selected city does not exist.',
                'state.required_without' => 'State is required when address_id is not provided.',
                'state.max' => 'State cannot exceed 255 characters.',
                'zip_code.required_without' => 'Zip code is required when address_id is not provided.',
                'zip_code.max' => 'Zip code cannot exceed 50 characters.',
                'street_address.required_without' => 'Street address is required when address_id is not provided.',
                'phone.required' => 'Phone number is required for order delivery.',
                'phone.max' => 'Phone number cannot exceed 255 characters.',
                'email.email' => 'Please provide a valid email address.',
                'email.max' => 'Email cannot exceed 255 characters.',
            ]);

            DB::beginTransaction();

            $userId = $request->user_id;
            $guestId = $request->guest_id;

            // Validate that at least one of user_id or guest_id is provided
            if (!$userId && !$guestId) {
                DB::rollBack();
                return $this->errorResponse(
                    'Either user_id or guest_id must be provided to create an order.',
                    422
                );
            }

            // Validate user exists if user_id is provided
            if ($userId) {
                $user = User::find($userId);
                if (!$user) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'User with ID ' . $userId . ' does not exist. Please provide a valid user_id.',
                        404
                    );
                }
            }

            // Get cart items
            $cartItems = [];
            if ($request->filled('cart_id')) {
                // Get single cart item
                $cart = Cart::with(['item', 'price'])->find($request->cart_id);
                if (!$cart) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Cart item with ID ' . $request->cart_id . ' not found. Please provide a valid cart_id.',
                        404
                    );
                }
                // Validate cart belongs to user/guest
                if ($userId && $cart->user_id != $userId) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Cart item with ID ' . $request->cart_id . ' does not belong to user with ID ' . $userId . '. You can only create orders from your own cart items.',
                        403
                    );
                }
                if ($guestId && $cart->guest_id != $guestId) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Cart item with ID ' . $request->cart_id . ' does not belong to guest with ID ' . $guestId . '. You can only create orders from your own cart items.',
                        403
                    );
                }
                $cartItems = [$cart];
            } else {
                // Get all cart items for user/guest
                $cartQuery = Cart::with(['item', 'price']);
                if ($userId) {
                    $cartQuery->where('user_id', $userId)->whereNull('guest_id');
                } else {
                    $cartQuery->where('guest_id', $guestId)->whereNull('user_id');
                }
                $cartItems = $cartQuery->get();

                if ($cartItems->isEmpty()) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Your cart is empty. Please add items to your cart before creating an order.',
                        404
                    );
                }
            }

            // Group cart items by item_id and price_id to calculate quantity
            $groupedItems = [];
            $firstCartId = null;
            $discountCoupon = null;
            $invalidCarts = [];
            
            foreach ($cartItems as $cart) {
                if (!$firstCartId) {
                    $firstCartId = $cart->id;
                    $discountCoupon = $cart->discount_coupon;
                }
                
                // Validate cart item has valid item and price
                if (!$cart->item) {
                    $invalidCarts[] = 'Cart ID ' . $cart->id . ' has invalid item (item may have been deleted)';
                    continue;
                }
                
                if (!$cart->price) {
                    $invalidCarts[] = 'Cart ID ' . $cart->id . ' has invalid price (price may have been deleted)';
                    continue;
                }

                $key = $cart->item_id . '_' . $cart->price_id;
                if (!isset($groupedItems[$key])) {
                    $groupedItems[$key] = [
                        'item_id' => $cart->item_id,
                        'price_id' => $cart->price_id,
                        'quantity' => 0,
                        'price' => (float) $cart->price->price,
                    ];
                }
                $groupedItems[$key]['quantity']++;
            }

            if (empty($groupedItems)) {
                DB::rollBack();
                $errorMessage = 'No valid items found in cart. ';
                if (!empty($invalidCarts)) {
                    $errorMessage .= implode(' ', $invalidCarts) . ' Please remove invalid items from your cart and try again.';
                } else {
                    $errorMessage .= 'Please add valid items to your cart and try again.';
                }
                return $this->errorResponse($errorMessage, 404);
            }

            // Warn about invalid carts but continue if we have valid items
            if (!empty($invalidCarts)) {
                // Log or handle invalid carts - for now we continue with valid items
            }

            // Calculate items price
            $itemsPrice = 0;
            foreach ($groupedItems as $group) {
                $itemsPrice += $group['price'] * $group['quantity'];
            }

            // Get restaurant data for tax and delivery_charge
            $restaurant = Restaurant::first();
            if (!$restaurant) {
                DB::rollBack();
                return $this->errorResponse(
                    'Restaurant configuration is not set up. Please contact the administrator to configure restaurant settings before placing an order.',
                    404
                );
            }

            $taxPercentage = (float) $restaurant->tax;
            $deliveryCharge = (float) $restaurant->delivery_charge;

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

            // Calculate total amount: items_price + tax + delivery - discount
            $totalAmount = $itemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
            // Ensure total amount doesn't go below 0
            if ($totalAmount < 0) {
                $totalAmount = 0;
            }

            // Handle address
            $addressId = $request->address_id;
            if (!$addressId) {
                // Validate that address fields are provided
                if (!$request->filled('city_id') || !$request->filled('state') || 
                    !$request->filled('zip_code') || !$request->filled('street_address')) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Address information is required. Please provide either address_id or complete address details (city_id, state, zip_code, street_address).',
                        422
                    );
                }

                // Create new address if address_id not provided
                if ($userId) {
                    // For registered users, create address record
                    try {
                        $address = Address::create([
                            'user_id' => $userId,
                            'city_id' => $request->city_id,
                            'state' => $request->state,
                            'zip_code' => $request->zip_code,
                            'street_address' => $request->street_address,
                        ]);
                        $addressId = $address->id;
                    } catch (\Exception $e) {
                        DB::rollBack();
                        return $this->errorResponse(
                            'Failed to create address: ' . $e->getMessage() . '. Please check your address details and try again.',
                            500
                        );
                    }
                }
                // For guest orders (no user_id), address_id will remain null
                // Address information is still required for delivery but not stored in addresses table
            } else {
                // Validate address exists
                $address = Address::find($addressId);
                if (!$address) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Address with ID ' . $addressId . ' not found. Please provide a valid address_id or create a new address.',
                        404
                    );
                }

                // Validate address belongs to user if user_id is provided
                if ($userId && $address->user_id != $userId) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Address with ID ' . $addressId . ' does not belong to user with ID ' . $userId . '. You can only use your own addresses.',
                        403
                    );
                }
                // For guest orders, address_id can be provided but we don't validate ownership
            }

            // Get email from user if not provided
            $email = $request->email;
            if (!$email && $userId) {
                $user = User::find($userId);
                $email = $user ? $user->email : null;
            }

            // Create order
            try {
                $order = Order::create([
                    'user_id' => $userId,
                    'guest_id' => $guestId,
                    'cart_id' => $firstCartId,
                    'address_id' => $addressId,
                    'total_amount' => round($totalAmount, 2),
                    'status' => Order::STATUS_PENDING,
                    'phone' => $request->phone,
                    'email' => $email,
                    'notes' => $request->notes,
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse(
                    'Failed to create order: ' . $e->getMessage() . '. Please try again or contact support if the problem persists.',
                    500
                );
            }

            // Create order items
            $orderItemsCreated = 0;
            foreach ($groupedItems as $group) {
                try {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_id' => $group['item_id'],
                        'price_id' => $group['price_id'],
                        'quantity' => $group['quantity'],
                        'price' => $group['price'],
                    ]);
                    $orderItemsCreated++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    return $this->errorResponse(
                        'Failed to create order item for item ID ' . $group['item_id'] . ': ' . $e->getMessage() . '. Please try again.',
                        500
                    );
                }
            }

            // Validate that all order items were created
            if ($orderItemsCreated === 0) {
                DB::rollBack();
                return $this->errorResponse(
                    'Failed to create order items. No items were added to the order. Please try again.',
                    500
                );
            }

            DB::commit();

            // Load relationships
            $order->load(['user', 'address.city', 'orderItems.item', 'orderItems.priceModel']);

            return $this->successResponse([
                'order' => $order,
            ], 'Order created successfully', 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (QueryException $e) {
            DB::rollBack();
            // Handle database errors
            $errorCode = $e->getCode();
            if ($errorCode == 23000) { // Integrity constraint violation
                return $this->errorResponse(
                    'Database integrity error: The order could not be created due to data constraints. Please check your input and try again.',
                    422
                );
            }
            return $this->errorResponse(
                'Database error occurred while creating the order. Please try again or contact support if the problem persists. Error: ' . $e->getMessage(),
                500
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'An unexpected error occurred while creating the order: ' . $e->getMessage() . '. Please try again or contact support if the problem persists.',
                500
            );
        }
    }
}
