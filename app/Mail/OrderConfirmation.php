<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public $order;
    public $orderData;
    public $trackOrderUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Order $order, array $orderData)
    {
        $this->order = $order;
        $this->orderData = $orderData;
        
        // Generate track order URL with base64 encoded order details
        $frontendUrl = env('FRONTEND_URL', '');
        if ($frontendUrl) {
            $encodedOrderDetails = base64_encode(json_encode($orderData));
            $this->trackOrderUrl = rtrim($frontendUrl, '/') . '/track?order_details=' . urlencode($encodedOrderDetails);
        } else {
            $this->trackOrderUrl = '#';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address', 'noreply@example.com'),
            subject: 'Order Confirmation - Order #' . $this->order->id,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'order' => $this->order,
                'orderData' => $this->orderData,
                'trackOrderUrl' => $this->trackOrderUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
