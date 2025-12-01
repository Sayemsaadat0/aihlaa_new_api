<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TwilioController extends Controller
{
    private $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Send WhatsApp/SMS message
     * 
     * Accepts either a custom message or order notification data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required_without:order_data|string|max:1000',
                'to' => 'nullable|string|max:20',
                'order_data' => 'required_without:message|array',
                'order_data.order_id' => 'required_with:order_data|integer',
                'order_data.order_number' => 'required_with:order_data|string',
                'order_data.customer_name' => 'required_with:order_data|string',
                'order_data.customer_phone' => 'nullable|string',
                'order_data.total_amount' => 'required_with:order_data|numeric',
                'order_data.payment_method' => 'nullable|string',
                'order_data.delivery_type' => 'nullable|string',
                'order_data.created_at' => 'nullable|string',
                'order_data.order_items' => 'nullable|array',
                'order_data.order_items.*.name' => 'required_with:order_data.order_items|string',
                'order_data.order_items.*.quantity' => 'required_with:order_data.order_items|integer',
                'order_data.order_items.*.total_price' => 'required_with:order_data.order_items|numeric',
                'order_data.delivery_address' => 'nullable|array',
                'order_data.special_instructions' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $success = false;
            $message = '';
            $type = '';

            if ($request->has('message')) {
                // Send custom message
                $to = $request->input('to'); // Optional: send to specific number
                if ($to) {
                    $success = $this->twilioService->sendMessage($to, $request->message);
                } else {
                    $success = $this->twilioService->sendCustomMessage($request->message);
                }
                $message = $success ? 'Message sent successfully' : 'Failed to send message';
                $type = 'custom_message';
            } elseif ($request->has('order_data')) {
                // Send order notification
                $success = $this->twilioService->sendOrderNotification($request->order_data);
                $message = $success ? 'Order notification sent successfully' : 'Failed to send order notification';
                $type = 'order_notification';
            }

            return response()->json([
                'success' => $success,
                'message' => $message,
                'data' => [
                    'timestamp' => now()->toISOString(),
                    'type' => $type
                ]
            ], $success ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while sending message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test Twilio connection
     * 
     * @return JsonResponse
     */
    public function test(): JsonResponse
    {
        try {
            $result = $this->twilioService->testConnection();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'timestamp' => now()->toISOString(),
                    'message_sid' => $result['message_sid'] ?? null,
                    'error' => $result['error'] ?? null
                ]
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while testing connection',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

