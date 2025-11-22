<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Send email API endpoint
     * POST /api/v1/emails/send
     */
    public function send(Request $request)
    {
        try {
            $validated = $request->validate([
                'to' => 'required|email|max:255',
                'type' => 'required|string|max:255',
                'data' => 'nullable|array',
            ], [
                'to.required' => 'Recipient email address is required.',
                'to.email' => 'Please provide a valid email address.',
                'to.max' => 'Email address cannot exceed 255 characters.',
                'type.required' => 'Email type is required.',
                'type.string' => 'Email type must be a string.',
                'type.max' => 'Email type cannot exceed 255 characters.',
                'data.array' => 'Data must be an array.',
            ]);

            $to = $validated['to'];
            $type = $validated['type'];
            $data = $validated['data'] ?? [];

            // Send email using EmailService
            $result = $this->emailService->send($to, $type, $data);

            if ($result['success']) {
                return $this->successResponse($result['data'], $result['message'], 200);
            } else {
                return $this->errorResponse(
                    $result['error'] ?? $result['message'],
                    400
                );
            }

        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to send email: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get available email types
     * GET /api/v1/emails/types
     */
    public function getTypes()
    {
        try {
            $types = $this->emailService->getAvailableTypes();
            
            return $this->successResponse([
                'types' => $types,
                'total' => count($types),
            ], 'Available email types retrieved successfully', 200);

        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve email types: ' . $e->getMessage(),
                500
            );
        }
    }
}

