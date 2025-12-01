<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Exception;

class TwilioService
{
    private $twilioClient;
    private $fromNumber;
    private $toNumber;

    public function __construct()
    {
        $this->fromNumber = config('services.twilio.whatsapp_from');
        $this->toNumber = config('services.twilio.whatsapp_to');

        // Graceful degradation if SDK not installed
        if (!class_exists(\Twilio\Rest\Client::class)) {
            Log::warning('Twilio SDK not installed; messaging disabled. Run: composer require twilio/sdk');
            $this->twilioClient = null;
            return;
        }

        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.auth_token');
        
        if (empty($sid) || empty($token)) {
            Log::warning('Twilio credentials missing; messaging disabled. Set TWILIO_SID and TWILIO_AUTH_TOKEN in .env');
            $this->twilioClient = null;
            return;
        }

        try {
            $this->twilioClient = new Client($sid, $token);
        } catch (\Throwable $e) {
            Log::error('Failed to initialize Twilio client', ['error' => $e->getMessage()]);
            $this->twilioClient = null;
        }
    }

    /**
     * Send WhatsApp/SMS message to configured number
     */
    public function sendMessage(string $to, string $message): bool
    {
        if (!$this->twilioClient) {
            Log::info('Skipping message send (Twilio not configured)');
            return false;
        }

        try {
            $formattedTo = $this->formatPhoneNumber($to);
            
            $messageResponse = $this->twilioClient->messages->create(
                $formattedTo,
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );

            Log::info('Message sent successfully', [
                'to' => $formattedTo,
                'message_sid' => $messageResponse->sid,
                'message_length' => strlen($message)
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send message', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send order notification message (Hook function)
     * This is the main hook to call after order creation
     */
    public function sendOrderNotification(array $orderData): bool
    {
        if (!$this->twilioClient) {
            Log::warning('WhatsApp notification skipped - Twilio not configured', [
                'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
                'check' => 'TWILIO_SID and TWILIO_AUTH_TOKEN must be set in .env'
            ]);
            return false;
        }

        if (empty($this->toNumber)) {
            Log::warning('WhatsApp notification skipped - TWILIO_WHATSAPP_NUMBER_TO not configured', [
                'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
            ]);
            return false;
        }

        try {
            $message = $this->formatOrderNotification($orderData);
            // Use configured WhatsApp number from env
            $phoneNumber = $this->toNumber;
            
            Log::info('Sending WhatsApp order notification', [
                'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
                'to' => $phoneNumber,
                'message_length' => strlen($message)
            ]);
            
            $result = $this->sendMessage($phoneNumber, $message);
            
            if ($result) {
                Log::info('WhatsApp order notification sent successfully', [
                    'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
                ]);
            } else {
                Log::error('WhatsApp order notification failed to send', [
                    'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            Log::error('Failed to send order notification', [
                'order_id' => $orderData['id'] ?? $orderData['order_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Hook function: Send WhatsApp notification after order creation.
     *
     * @param mixed $order   Order data (model instance or array)
     * @param array $cartDetails Snapshot of cart details at order creation
     */
    public function sendOrderWhatsAppHook($order, array $cartDetails = []): bool
    {
        try {
            // Reload order with relationships
            if (is_object($order)) {
                $order->load(['user', 'city', 'orderItems.item', 'orderItems.price']);
            }

            // Prepare order data for notification
            $orderNotificationData = [
                'id' => $order->id ?? $order['id'] ?? null,
                'order_number' => (string) ($order->id ?? $order['id'] ?? 'N/A'),
                'customer_name' => $this->getCustomerName($order),
                'customer_phone' => $order->phone ?? $order['phone'] ?? null,
                'total_amount' => $order->total_amount ?? $order['total_amount'] ?? 0,
                'payable_amount' => $order->total_amount ?? $order['total_amount'] ?? 0,
                'created_at' => isset($order->created_at) 
                    ? $order->created_at->format('Y-m-d H:i:s') 
                    : ($order['created_at'] ?? now()->format('Y-m-d H:i:s')),
                'cart_details' => $this->prepareCartDetails($order, $cartDetails),
                'street_address' => $order->street_address ?? $order['street_address'] ?? '',
                'state' => $order->state ?? $order['state'] ?? '',
                'zip_code' => $order->zip_code ?? $order['zip_code'] ?? '',
                'city_details' => $this->getCityDetails($order),
                'notes' => $order->notes ?? $order['notes'] ?? null,
                'user_info' => $this->getUserInfo($order),
            ];

            return $this->sendOrderNotification($orderNotificationData);
        } catch (Exception $e) {
            Log::error('WhatsApp hook failed', [
                'order_id' => $order->id ?? $order['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get customer name from order
     */
    private function getCustomerName($order): string
    {
        if (is_object($order) && $order->user) {
            return $order->user->name ?? 'Guest';
        }
        if (isset($order['user']) && is_array($order['user'])) {
            return $order['user']['name'] ?? 'Guest';
        }
        if (isset($order['user_info']) && is_array($order['user_info'])) {
            return $order['user_info']['name'] ?? 'Guest';
        }
        return 'Guest';
    }

    /**
     * Get city details from order
     */
    private function getCityDetails($order): ?array
    {
        if (is_object($order) && $order->city) {
            return ['name' => $order->city->name ?? null];
        }
        if (isset($order['city']) && is_array($order['city'])) {
            return ['name' => $order['city']['name'] ?? null];
        }
        if (isset($order['city_details']) && is_array($order['city_details'])) {
            return $order['city_details'];
        }
        return null;
    }

    /**
     * Get user info from order
     */
    private function getUserInfo($order): ?array
    {
        if (is_object($order) && $order->user) {
            return ['name' => $order->user->name ?? null];
        }
        if (isset($order['user_info']) && is_array($order['user_info'])) {
            return $order['user_info'];
        }
        return null;
    }

    /**
     * Prepare cart details from order
     */
    private function prepareCartDetails($order, array $cartDetails = []): array
    {
        // If cart details are provided, use them
        if (!empty($cartDetails) && isset($cartDetails['items'])) {
            return $cartDetails;
        }

        // Otherwise, try to get from order items
        $items = [];
        if (is_object($order) && isset($order->orderItems)) {
            foreach ($order->orderItems as $orderItem) {
                $items[] = [
                    'title' => $orderItem->item->name ?? 'Unknown Item',
                    'quantity' => $orderItem->quantity ?? 1,
                    'price' => [
                        'price' => $orderItem->price ?? 0
                    ]
                ];
            }
        } elseif (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $orderItem) {
                $items[] = [
                    'title' => $orderItem['item']['name'] ?? $orderItem['name'] ?? 'Unknown Item',
                    'quantity' => $orderItem['quantity'] ?? 1,
                    'price' => [
                        'price' => $orderItem['price'] ?? 0
                    ]
                ];
            }
        }

        return [
            'items' => $items,
            'items_price' => $order->total_amount ?? $order['total_amount'] ?? 0,
        ];
    }

    /**
     * Send custom message to configured number
     */
    public function sendCustomMessage(string $message): bool
    {
        if (!$this->twilioClient) {
            Log::info('Skipping custom message (Twilio not configured)');
            return false;
        }

        return $this->sendMessage($this->toNumber, $message);
    }

    /**
     * Test Twilio connection
     */
    public function testConnection(): array
    {
        if (!$this->twilioClient) {
            return [
                'success' => false,
                'message' => 'Twilio not configured',
            ];
        }

        try {
            $testMessage = "🧪 Twilio Integration Test - " . now()->format('Y-m-d H:i:s');
            
            $messageResponse = $this->twilioClient->messages->create(
                $this->toNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $testMessage
                ]
            );

            return [
                'success' => true,
                'message' => 'Connection test successful',
                'message_sid' => $messageResponse->sid
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format order notification message
     *
     * This uses the same structure as the API response built in OrderController@store.
     * It includes customer info, full address, phone, all items with prices,
     * and a full price breakdown (items, discount, tax, delivery, total).
     */
    private function formatOrderNotification(array $orderData): string
    {
        $orderNumber = $orderData['order_number'] ?? $orderData['id'] ?? 'N/A';
        $customerName = $orderData['customer_name'] ?? ($orderData['user_info']['name'] ?? 'Guest');
        $phone = $orderData['phone'] ?? $orderData['customer_phone'] ?? null;
        $totalAmount = $orderData['total_amount'] ?? $orderData['payable_amount'] ?? 0.0;
        $timestamp = $orderData['created_at'] ?? now()->format('Y-m-d H:i:s');

        $cart = $orderData['cart_details'] ?? [];
        $items = $cart['items'] ?? [];
        $itemsPrice = $cart['items_price'] ?? 0.0;
        $discount = $cart['discount']['amount'] ?? 0.0;
        $discountCoupon = $cart['discount']['coupon'] ?? null;
        $taxPrice = $cart['charges']['tax_price'] ?? 0.0;
        $deliveryCharges = $cart['charges']['delivery_charges'] ?? 0.0;
        $payableAmount = $orderData['payable_amount'] ?? $totalAmount;

        $message = "📦 *New Order Received*\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Customer: {$customerName}\n";
        if ($phone) {
            $message .= "Phone: {$phone}\n";
        }
        $message .= "Time: {$timestamp}\n\n";

        // Order items
        if (!empty($items)) {
            $message .= "*Items:*\n";
            foreach ($items as $item) {
                $itemName = $item['title'] ?? 'Unknown';
                $quantity = $item['quantity'] ?? 1;
                $unitPrice = $item['price']['price'] ?? 0;
                $lineTotal = $quantity * $unitPrice;
                $message .= sprintf(
                    "• %s x%d @ $%0.2f = $%0.2f\n",
                    $itemName,
                    $quantity,
                    $unitPrice,
                    $lineTotal
                );
            }
            $message .= "\n";
        }

        // Price breakdown
        $message .= "*Price Summary:*\n";
        $message .= "Items: $" . number_format((float) $itemsPrice, 2) . "\n";
        if ($discount > 0) {
            $label = $discountCoupon ? "Discount ({$discountCoupon})" : 'Discount';
            $message .= "{$label}: -$" . number_format((float) $discount, 2) . "\n";
        }
        $message .= "Tax: $" . number_format((float) $taxPrice, 2) . "\n";
        $message .= "Delivery: $" . number_format((float) $deliveryCharges, 2) . "\n";
        $message .= "-------------------------\n";
        $message .= "*Total: $" . number_format((float) $payableAmount, 2) . "*\n\n";

        // Delivery address
        if (!empty($orderData['street_address'])) {
            $message .= "*Delivery Address:*\n";
            $message .= ($orderData['street_address'] ?? '') . "\n";
            if (!empty($orderData['city_details']['name'])) {
                $message .= ($orderData['city_details']['name'] ?? '') . "\n";
            }
            $message .= trim(($orderData['state'] ?? '') . ' ' . ($orderData['zip_code'] ?? '')) . "\n\n";
        }

        // Notes
        if (!empty($orderData['notes'])) {
            $message .= "*Special Instructions:*\n";
            $message .= $orderData['notes'] . "\n";
        }

        return $message;
    }

    /**
     * Format phone number for Twilio
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If it's already in WhatsApp format, return as is
        if (strpos($phone, 'whatsapp:') === 0) {
            return $phone;
        }
        
        // If it starts with +, assume it's already formatted
        if (strpos($phone, '+') === 0) {
            return 'whatsapp:' . $phone;
        }
        
        // Remove leading zeros and add country code if needed (assuming US +1)
        $phone = ltrim($phone, '0');
        if (strlen($phone) === 10) {
            $phone = '1' . $phone;
        }
        
        return 'whatsapp:+' . $phone;
    }
}

