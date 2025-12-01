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
     * Send order notification message
     */
    public function sendOrderNotification(array $orderData): bool
    {
        if (!$this->twilioClient) {
            Log::info('Skipping order notification (Twilio not configured)');
            return false;
        }

        try {
            $message = $this->formatOrderNotification($orderData);
            $phoneNumber = $this->formatPhoneNumber($orderData['customer_phone'] ?? $this->toNumber);
            
            return $this->sendMessage($phoneNumber, $message);
        } catch (Exception $e) {
            Log::error('Failed to send order notification', [
                'order_id' => $orderData['order_id'] ?? null,
                'error' => $e->getMessage()
            ]);
            return false;
        }
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
     */
    private function formatOrderNotification(array $orderData): string
    {
        $orderNumber = $orderData['order_number'] ?? 'N/A';
        $customerName = $orderData['customer_name'] ?? 'Guest';
        $totalAmount = $orderData['total_amount'] ?? '0.00';
        $timestamp = $orderData['created_at'] ?? now()->format('Y-m-d H:i:s');
        
        $message = "📦 *New Order Received*\n\n";
        $message .= "Order #: {$orderNumber}\n";
        $message .= "Customer: {$customerName}\n";
        $message .= "Total: $" . number_format($totalAmount, 2) . "\n";
        $message .= "Time: {$timestamp}\n\n";
        
        if (isset($orderData['order_items']) && !empty($orderData['order_items'])) {
            $message .= "Items:\n";
            foreach ($orderData['order_items'] as $item) {
                $itemName = $item['name'] ?? 'Unknown';
                $quantity = $item['quantity'] ?? 1;
                $totalPrice = $item['total_price'] ?? 0;
                $message .= "• {$itemName} x{$quantity} - $" . number_format($totalPrice, 2) . "\n";
            }
            $message .= "\n";
        }
        
        if (isset($orderData['delivery_address']) && !empty($orderData['delivery_address'])) {
            $address = $orderData['delivery_address'];
            $message .= "Delivery Address:\n";
            $message .= ($address['address_line_1'] ?? '') . "\n";
            if (!empty($address['city'])) {
                $message .= ($address['city'] ?? '') . "\n";
            }
            $message .= ($address['post_code'] ?? '') . "\n\n";
        }
        
        if (!empty($orderData['special_instructions'])) {
            $message .= "Special Instructions: {$orderData['special_instructions']}\n";
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

