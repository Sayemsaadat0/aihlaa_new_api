<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Restaurant;
use App\Models\Discount;
use App\Models\User;
use App\Models\Item;
use App\Models\ItemPrice;
use App\Mail\OrderConfirmation;
use App\Services\EmailService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\QueryException;

class OrderController extends Controller
{
    /**
     * Create a new order (Public API)
     */
    public function store(Request $request)
    {
        try {
            // Custom validation: at least one of user_id or guest_id must be present
            // This is more reliable than required_without in production environments
            $hasUserId = $request->has('user_id') && $request->filled('user_id');
            $hasGuestId = $request->has('guest_id') && $request->filled('guest_id');
            
            if (!$hasUserId && !$hasGuestId) {
                return $this->errorResponse(
                    'Either user_id or guest_id is required.',
                    422
                );
            }
            
            // Validate request with custom error messages
            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id',
                'guest_id' => 'nullable|string|max:255',
                'cart_id' => 'required|integer|exists:carts,id',
                'city_id' => 'required|integer|exists:cities,id',
                'state' => 'required|string|max:255',
                'zip_code' => 'required|string|max:50',
                'street_address' => 'required|string',
                'phone' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'notes' => 'nullable|string',
            ], [
                'user_id.exists' => 'The selected user does not exist.',
                'guest_id.max' => 'Guest ID cannot exceed 255 characters.',
                'cart_id.required' => 'Cart ID is required.',
                'cart_id.exists' => 'The selected cart does not exist.',
                'city_id.required' => 'City ID is required.',
                'city_id.exists' => 'The selected city does not exist.',
                'state.required' => 'State is required.',
                'state.max' => 'State cannot exceed 255 characters.',
                'zip_code.required' => 'Zip code is required.',
                'zip_code.max' => 'Zip code cannot exceed 50 characters.',
                'street_address.required' => 'Street address is required.',
                'phone.required' => 'Phone number is required for order delivery.',
                'phone.max' => 'Phone number cannot exceed 255 characters.',
                'email.required' => 'Email address is required for order confirmation.',
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

            // Get cart items - cart_id is required
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
            
            // Get all cart items for the same user/guest to build cart details
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

            // Get email from request (required field)
            $email = $request->email;

            // Create order
            try {
                $order = Order::create([
                    'user_id' => $userId,
                    'guest_id' => $guestId,
                    'cart_id' => $firstCartId,
                    'city_id' => $request->city_id,
                    'state' => $request->state,
                    'zip_code' => $request->zip_code,
                    'street_address' => $request->street_address,
                    'total_amount' => round($totalAmount, 2),
                    'status' => Order::STATUS_PENDING,
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
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

            // Delete all cart items for the user/guest after order is created
            // All cart data is now stored in order_items table
            try {
                $cartDeleteQuery = Cart::query();
                if ($userId) {
                    $cartDeleteQuery->where('user_id', $userId)->whereNull('guest_id');
                } else {
                    $cartDeleteQuery->where('guest_id', $guestId)->whereNull('user_id');
                }
                $deletedCount = $cartDeleteQuery->delete();
                
                if ($deletedCount === 0) {
                    // This shouldn't happen, but log it (cart items should exist)
                    // We'll continue anyway since order is created
                }
            } catch (\Exception $e) {
                DB::rollBack();
                return $this->errorResponse(
                    'Failed to clear cart after order creation: ' . $e->getMessage() . '. Order was created but cart could not be cleared. Please contact support.',
                    500
                );
            }

            DB::commit();

            // Load relationships
            $order->load(['user', 'city']);

            // Build cart details similar to cart API response
            $items = [];
            $itemsPrice = 0;
            $discountCoupon = $cartItems->first()->discount_coupon ?? '';

            // Group cart items by item_id and price_id
            $groupedCartItems = [];
            foreach ($cartItems as $cartItem) {
                if (!$cartItem->item || !$cartItem->price) {
                    continue;
                }
                $key = $cartItem->item_id . '_' . $cartItem->price_id;
                if (!isset($groupedCartItems[$key])) {
                    $groupedCartItems[$key] = [
                        'item' => $cartItem->item,
                        'price' => $cartItem->price,
                        'quantity' => 0,
                    ];
                }
                $groupedCartItems[$key]['quantity']++;
            }

            foreach ($groupedCartItems as $group) {
                $item = $group['item'];
                $price = $group['price'];
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

            // Get restaurant data for tax and delivery_charge
            $restaurant = Restaurant::first();
            $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
            $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;

            // Calculate tax price
            $taxPrice = ($itemsPrice * $taxPercentage) / 100;

            // Calculate discount amount
            $discountAmount = 0;
            if (!empty($discountCoupon)) {
                $discount = Discount::where('code', $discountCoupon)
                    ->where('status', Discount::STATUS_PUBLISHED)
                    ->first();
                
                if ($discount) {
                    $discountAmount = (float) $discount->discount_price;
                    $totalBeforeDiscount = $itemsPrice + $taxPrice + $deliveryCharge;
                    if ($discountAmount > $totalBeforeDiscount) {
                        $discountAmount = $totalBeforeDiscount;
                    }
                }
            }

            // Calculate payable price
            $payablePrice = $itemsPrice + $taxPrice + $deliveryCharge - $discountAmount;
            if ($payablePrice < 0) {
                $payablePrice = 0;
            }

            // Build cart_details
            $cartDetails = [
                'cart_id' => $firstCartId,
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
            ];

            // Build response
            $response = [
                'id' => $order->id,
                'order_id' => $order->id,
                'guest_id' => $order->guest_id,
                'payment_status' => $order->payment_status,
                'user_info' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'cart_details' => $cartDetails,
                'phone' => $order->phone,
                'city_id' => $order->city_id,
                'city_details' => $order->city ? [
                    'id' => $order->city->id,
                    'name' => $order->city->name,
                    'status' => $order->city->status,
                ] : null,
                'state' => $order->state,
                'zip_code' => $order->zip_code,
                'street_address' => $order->street_address,
                'payable_amount' => round($order->total_amount, 2),
                'status' => $order->status,
                'created_at' => $order->created_at->toDateTimeString(),
                'updated_at' => $order->updated_at->toDateTimeString(),
            ];

            // Send order confirmation email using EmailService
            try {
                $emailService = app(EmailService::class);
                $emailResult = $emailService->send($email, 'order_confirmation', [
                    'order' => $order,
                    'orderData' => $response,
                ]);
                
                if (!$emailResult['success']) {
                    \Log::warning('Order confirmation email failed', [
                        'order_id' => $order->id,
                        'email' => $email,
                        'error' => $emailResult['error'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                // Log email error but don't fail the order creation
                \Log::error('Failed to send order confirmation email', [
                    'order_id' => $order->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // Call the public Twilio send API with the full order data
            // This will send WhatsApp to the admin number configured in TWILIO_WHATSAPP_NUMBER_TO
            try {
                $twilioEndpoint = url('/api/twilio/send');

                $twilioResponse = Http::post($twilioEndpoint, [
                    // Pass the same structure as the order API response
                    // TwilioController will format it into a WhatsApp message
                    'order_data' => $response,
                ]);

                if (!$twilioResponse->successful()) {
                    \Log::warning('Twilio /api/twilio/send call failed after order creation', [
                        'order_id' => $order->id,
                        'status' => $twilioResponse->status(),
                        'body' => $twilioResponse->body(),
                    ]);
                } else {
                    \Log::info('Twilio /api/twilio/send called successfully after order creation', [
                        'order_id' => $order->id,
                    ]);
                }
            } catch (\Exception $e) {
                // Log Twilio error but don't fail the order creation
                \Log::error('Failed to call /api/twilio/send after order creation', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $this->successResponse($response, 'Order created successfully', 201);

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

    /**
     * Get order by ID (Public API)
     */
    public function show(Request $request, $id)
    {
        try {
            $order = Order::with(['user', 'city', 'orderItems.item', 'orderItems.priceModel'])->find($id);

            if (!$order) {
                return $this->errorResponse(
                    'Order with ID ' . $id . ' not found.',
                    404
                );
            }

            // Optional: Validate order belongs to user/guest if provided
            $userId = $request->query('user_id');
            $guestId = $request->query('guest_id');

            if ($userId && $order->user_id != $userId) {
                return $this->errorResponse(
                    'Order does not belong to the specified user.',
                    403
                );
            }

            if ($guestId && $order->guest_id != $guestId) {
                return $this->errorResponse(
                    'Order does not belong to the specified guest.',
                    403
                );
            }

            // Build cart details from order items
            $items = [];
            $itemsPrice = 0;
            $discountCoupon = null;
            $discountAmount = 0;

            foreach ($order->orderItems as $orderItem) {
                if ($orderItem->item && $orderItem->priceModel) {
                    $itemPrice = (float) $orderItem->price;
                    $itemsPrice += $itemPrice * $orderItem->quantity;
                    
                    $items[] = [
                        'id' => $orderItem->item->id,
                        'title' => $orderItem->item->name,
                        'quantity' => $orderItem->quantity,
                        'price' => [
                            'id' => $orderItem->priceModel->id,
                            'price' => $itemPrice,
                            'size' => $orderItem->priceModel->size,
                        ],
                    ];
                }
            }

            // Get restaurant data for tax and delivery_charge
            $restaurant = Restaurant::first();
            $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
            $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;

            // Calculate tax price
            $taxPrice = ($itemsPrice * $taxPercentage) / 100;

            // Calculate discount (if any was applied)
            // Note: We don't store discount in order_items, so we calculate from total
            $totalBeforeDiscount = $itemsPrice + $taxPrice + $deliveryCharge;
            if ($totalBeforeDiscount > $order->total_amount) {
                $discountAmount = $totalBeforeDiscount - $order->total_amount;
            }

            // Build cart_details
            $cartDetails = [
                'cart_id' => $order->cart_id,
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
                'payable_price' => round($order->total_amount, 2),
            ];

            // Build response
            $response = [
                'id' => $order->id,
                'order_id' => $order->id,
                'guest_id' => $order->guest_id,
                'payment_status' => $order->payment_status,
                'user_info' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                ] : null,
                'cart_details' => $cartDetails,
                'phone' => $order->phone,
                'email' => $order->email,
                'city_id' => $order->city_id,
                'city_details' => $order->city ? [
                    'id' => $order->city->id,
                    'name' => $order->city->name,
                    'status' => $order->city->status,
                ] : null,
                'state' => $order->state,
                'zip_code' => $order->zip_code,
                'street_address' => $order->street_address,
                'payable_amount' => round($order->total_amount, 2),
                'status' => $order->status,
                'notes' => $order->notes,
                'created_at' => $order->created_at->toDateTimeString(),
                'updated_at' => $order->updated_at->toDateTimeString(),
            ];

            return $this->successResponse($response, 'Order retrieved successfully', 200);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve order: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * List orders with optional filters (Admin only)
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'sometimes|integer|exists:users,id',
                'guest_id' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:' . implode(',', [
                    Order::STATUS_PENDING,
                    Order::STATUS_COOKING,
                    Order::STATUS_ON_THE_WAY,
                    Order::STATUS_DELIVERED,
                ]),
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $ordersQuery = Order::with([
                    'user:id,name,email',
                    'city:id,name,status',
                    'orderItems.item:id,name,details',
                    'orderItems.priceModel:id,item_id,price,size',
                ])
                ->withCount('orderItems')
                ->orderByDesc('created_at');

            if (isset($validated['user_id'])) {
                $ordersQuery->where('user_id', $validated['user_id']);
            }

            if (isset($validated['guest_id'])) {
                $ordersQuery->where('guest_id', $validated['guest_id']);
            }

            if (isset($validated['status'])) {
                $ordersQuery->where('status', $validated['status']);
            }

            $perPage = $validated['per_page'] ?? 15;
            $orders = $ordersQuery->paginate($perPage);

            $restaurant = Restaurant::first();
            $taxPercentage = $restaurant ? (float) $restaurant->tax : 0;
            $deliveryCharge = $restaurant ? (float) $restaurant->delivery_charge : 0;

            $ordersCollection = $orders->getCollection()->map(function ($order) use ($taxPercentage, $deliveryCharge) {
                $items = $order->orderItems->map(function ($orderItem) {
                    $itemPrice = (float) $orderItem->price;

                    return [
                        'id' => $orderItem->item?->id,
                        'title' => $orderItem->item?->name,
                        'description' => $orderItem->item?->details,
                        'quantity' => $orderItem->quantity,
                        'price' => [
                            'id' => $orderItem->priceModel?->id,
                            'price' => $itemPrice,
                            'size' => $orderItem->priceModel?->size,
                        ],
                    ];
                })->values();

                $itemsPrice = $order->orderItems->reduce(function ($carry, $orderItem) {
                    return $carry + ((float) $orderItem->price * $orderItem->quantity);
                }, 0);

                $taxPrice = ($itemsPrice * $taxPercentage) / 100;
                $totalBeforeDiscount = $itemsPrice + $taxPrice + $deliveryCharge;
                $discountAmount = 0;
                if ($totalBeforeDiscount > (float) $order->total_amount) {
                    $discountAmount = $totalBeforeDiscount - (float) $order->total_amount;
                }

                return [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'guest_id' => $order->guest_id,
                    'cart_id' => $order->cart_id,
                    'total_amount' => (float) $order->total_amount,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'phone' => $order->phone,
                    'email' => $order->email,
                    'notes' => $order->notes,
                    'created_at' => $order->created_at?->toDateTimeString(),
                    'updated_at' => $order->updated_at?->toDateTimeString(),
                    'user' => $order->user ? [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                    ] : null,
                    'address' => [
                        'state' => $order->state,
                        'zip_code' => $order->zip_code,
                        'street_address' => $order->street_address,
                        'city' => $order->city ? [
                            'id' => $order->city->id,
                            'name' => $order->city->name,
                            'status' => $order->city->status,
                        ] : null,
                    ],
                    'order_items' => $items,
                    'summary' => [
                        'items_price' => round($itemsPrice, 2),
                        'charges' => [
                            'tax' => round($taxPercentage, 2),
                            'tax_price' => round($taxPrice, 2),
                            'delivery_charges' => round($deliveryCharge, 2),
                            'discount' => round($discountAmount, 2),
                        ],
                        'payable_price' => round((float) $order->total_amount, 2),
                    ],
                    'order_items_count' => $order->order_items_count,
                ];
            })->values();

            $pagination = [
                'current_page' => $orders->currentPage(),
                'per_page' => (int) $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ];

            return $this->successResponse([
                'orders' => $ordersCollection,
                'pagination' => $pagination,
            ], 'Orders retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve orders: ' . $e->getMessage(),
                500
            );
        }
    }


    /**
     * Update order status or payment status (Admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'sometimes|in:' . implode(',', [
                    Order::STATUS_PENDING,
                    Order::STATUS_COOKING,
                    Order::STATUS_ON_THE_WAY,
                    Order::STATUS_DELIVERED,
                ]),
                'payment_status' => 'sometimes|in:' . implode(',', [
                    Order::PAYMENT_STATUS_UNPAID,
                    Order::PAYMENT_STATUS_PAID,
                ]),
            ]);

            if (!$request->hasAny(['status', 'payment_status'])) {
                return $this->errorResponse(
                    'Please provide at least one field: status or payment_status.',
                    422
                );
            }

            $order = Order::find($id);

            if (!$order) {
                return $this->notFoundResponse('Order');
            }

            $updates = array_filter([
                'status' => $validated['status'] ?? null,
                'payment_status' => $validated['payment_status'] ?? null,
            ], function ($value) {
                return $value !== null;
            });

            $order->update($updates);

            return $this->successResponse([
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'updated_at' => $order->updated_at?->toDateTimeString(),
                ],
            ], 'Order updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update order: ' . $e->getMessage(),
                500
            );
        }
    }
}
