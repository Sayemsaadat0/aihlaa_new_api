<?php

namespace App\Http\Controllers\Api;

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
                'message' => 'nullable|string|max:2000',
                'to' => 'nullable|string|max:50',
                'order_data' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Require at least one of message or order_data
            if (!$request->filled('message') && !$request->filled('order_data')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either message or order_data must be provided',
                    'errors' => [
                        'message' => ['The message field is required when order_data is not present.'],
                        'order_data' => ['The order_data field is required when message is not present.'],
                    ]
                ], 422);
            }

            $success = false;
            $messageText = '';
            $type = '';

            if ($request->filled('message')) {
                // Send custom message
                $to = $request->input('to'); // Optional: send to specific number
                if ($to) {
                    $success = $this->twilioService->sendMessage($to, $request->message);
                } else {
                    $success = $this->twilioService->sendCustomMessage($request->message);
                }
                $messageText = $success ? 'Message sent successfully' : 'Failed to send message';
                $type = 'custom_message';
            } elseif ($request->filled('order_data')) {
                // Send order notification
                $success = $this->twilioService->sendOrderNotification($request->order_data);
                $messageText = $success ? 'Order notification sent successfully' : 'Failed to send order notification';
                $type = 'order_notification';
            }

            return response()->json([
                'success' => $success,
                'message' => $messageText,
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

