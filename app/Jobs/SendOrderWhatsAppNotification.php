<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The ID of the order to notify about.
     *
     * @var int
     */
    public int $orderId;

    /**
     * Cart details snapshot at order creation time.
     *
     * @var array
     */
    public array $cartDetails;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId, array $cartDetails = [])
    {
        $this->orderId = $orderId;
        $this->cartDetails = $cartDetails;
    }

    /**
     * Execute the job.
     */
    public function handle(TwilioService $twilioService): void
    {
        $order = Order::with(['user', 'city', 'orderItems.item', 'orderItems.price'])
            ->find($this->orderId);

        if (!$order) {
            return;
        }

        // Use the existing hook in TwilioService – this always sends to TWILIO_WHATSAPP_NUMBER_TO
        $twilioService->sendOrderWhatsAppHook($order, $this->cartDetails);
    }
}


