<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Mail\WelcomeMail;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmailService
{
    /**
     * Map of email types to Mail classes
     */
    private const MAIL_CLASS_MAP = [
        'order_confirmation' => OrderConfirmation::class,
        'welcome' => WelcomeMail::class,
        'notification' => NotificationMail::class,
    ];

    /**
     * Send email by type
     *
     * @param string $to Recipient email address
     * @param string $type Email type (e.g., 'order_confirmation', 'welcome', 'notification')
     * @param array $data Data to pass to the Mail class constructor
     * @return array Result with success status, message, and data
     */
    public function send(string $to, string $type, array $data = []): array
    {
        try {
            // Validate email address
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address format',
                    'error' => 'The provided email address is not valid',
                ];
            }

            // Get Mail class from type
            $mailClass = $this->getMailClass($type);
            if (!$mailClass) {
                return [
                    'success' => false,
                    'message' => 'Invalid email type',
                    'error' => "Email type '{$type}' is not supported. Available types: " . implode(', ', array_keys(self::MAIL_CLASS_MAP)),
                ];
            }

            // Create Mail instance
            $mailInstance = $this->createMailInstance($mailClass, $data);
            if (!$mailInstance) {
                return [
                    'success' => false,
                    'message' => 'Failed to create email',
                    'error' => 'Could not instantiate mail class with provided data',
                ];
            }

            // Get subject from mail instance
            $subject = $this->getSubject($mailInstance);

            // Check if mail should be queued
            $isQueued = $this->shouldQueue($mailInstance);

            // Send email
            if ($isQueued) {
                Mail::to($to)->queue($mailInstance);
                $method = 'queued';
            } else {
                Mail::to($to)->send($mailInstance);
                $method = 'sent';
            }

            // Log success
            Log::info('Email sent successfully', [
                'to' => $to,
                'type' => $type,
                'subject' => $subject,
                'method' => $method,
            ]);

            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'data' => [
                    'to' => $to,
                    'subject' => $subject,
                    'type' => $type,
                    'method' => $method,
                ],
            ];

        } catch (\Exception $e) {
            // Log error
            Log::error('Failed to send email', [
                'to' => $to,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Mail class from type
     *
     * @param string $type
     * @return string|null
     */
    private function getMailClass(string $type): ?string
    {
        return self::MAIL_CLASS_MAP[$type] ?? null;
    }

    /**
     * Create Mail instance with data
     *
     * @param string $mailClass
     * @param array $data
     * @return \Illuminate\Mail\Mailable|null
     */
    private function createMailInstance(string $mailClass, array $data)
    {
        try {
            // Use reflection to get constructor parameters
            $reflection = new \ReflectionClass($mailClass);
            $constructor = $reflection->getConstructor();

            if (!$constructor) {
                // No constructor, create instance without parameters
                return new $mailClass();
            }

            $parameters = $constructor->getParameters();
            $args = [];

            foreach ($parameters as $param) {
                $paramName = $param->getName();
                $paramType = $param->getType();
                
                if (isset($data[$paramName])) {
                    $args[] = $data[$paramName];
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } elseif ($paramType && $paramType->getName() === 'array') {
                    // If parameter expects array and not provided, use empty array
                    $args[] = [];
                } else {
                    // Parameter is required but not provided
                    Log::warning('Missing required parameter for mail class', [
                        'mail_class' => $mailClass,
                        'parameter' => $paramName,
                        'type' => $paramType ? $paramType->getName() : 'unknown',
                    ]);
                    return null;
                }
            }

            return new $mailClass(...$args);

        } catch (\Exception $e) {
            Log::error('Failed to create mail instance', [
                'mail_class' => $mailClass,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get subject from mail instance
     *
     * @param \Illuminate\Mail\Mailable $mailInstance
     * @return string
     */
    private function getSubject($mailInstance): string
    {
        try {
            $envelope = $mailInstance->envelope();
            return $envelope->subject ?? 'No Subject';
        } catch (\Exception $e) {
            return 'Email';
        }
    }

    /**
     * Check if mail should be queued
     *
     * @param \Illuminate\Mail\Mailable $mailInstance
     * @return bool
     */
    private function shouldQueue($mailInstance): bool
    {
        return $mailInstance instanceof ShouldQueue;
    }

    /**
     * Get available email types
     *
     * @return array
     */
    public function getAvailableTypes(): array
    {
        return array_keys(self::MAIL_CLASS_MAP);
    }
}

