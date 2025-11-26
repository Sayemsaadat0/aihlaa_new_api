<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Order Confirmation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #000000;
            background-color: #f5f5f5;
            padding: 40px 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-body {
            padding: 60px 40px;
        }
        .header {
            margin-bottom: 50px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .header p {
            font-size: 14px;
            color: #666666;
            margin: 0;
        }
        .order-number {
            margin-bottom: 50px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e5e5e5;
        }
        .order-number h2 {
            font-size: 18px;
            font-weight: 400;
            color: #000000;
            margin-bottom: 4px;
        }
        .order-number p {
            font-size: 13px;
            color: #666666;
            margin: 0;
        }
        .section {
            margin-bottom: 40px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 500;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 14px;
            color: #666666;
        }
        .info-value {
            font-size: 14px;
            color: #000000;
            text-align: right;
        }
        .order-items {
            margin-top: 0;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-size: 14px;
            color: #000000;
            margin-bottom: 4px;
        }
        .item-meta {
            font-size: 13px;
            color: #666666;
        }
        .item-price {
            font-size: 14px;
            color: #000000;
            margin-left: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 14px;
        }
        .summary-row.total {
            border-top: 1px solid #000000;
            margin-top: 12px;
            padding-top: 16px;
            font-size: 16px;
        }
        .summary-label {
            color: #666666;
        }
        .summary-value {
            color: #000000;
        }
        .summary-row.total .summary-label,
        .summary-row.total .summary-value {
            color: #000000;
            font-weight: 500;
        }
        .track-button-container {
            text-align: center;
            margin: 50px 0 40px;
        }
        .track-button {
            display: inline-block;
            background-color: #000000;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: 1px solid #000000;
        }
        .track-button:hover {
            background-color: #333333;
        }
        .address-section {
            margin-top: 0;
        }
        .address-text {
            font-size: 14px;
            color: #000000;
            line-height: 1.8;
            margin-bottom: 16px;
        }
        .divider {
            height: 1px;
            background-color: #e5e5e5;
            margin: 40px 0;
        }
        .email-footer {
            padding: 40px;
            border-top: 1px solid #e5e5e5;
            text-align: center;
            font-size: 12px;
            color: #666666;
            line-height: 1.6;
        }
        .email-footer p {
            margin: 4px 0;
        }
        @media only screen and (max-width: 600px) {
            body {
                padding: 20px 10px;
            }
            .email-body {
                padding: 40px 24px;
            }
            .header h1 {
                font-size: 22px;
            }
            .track-button {
                padding: 12px 24px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-body">
            <!-- Header -->
            <div class="header">
                <h1>Order Confirmation</h1>
                <p>Thank you for your order</p>
            </div>

            <!-- Order Number -->
            <div class="order-number">
                <h2>Order #{{ $order->id }}</h2>
                <p>{{ $order->created_at->format('F d, Y \a\t h:i A') }}</p>
            </div>

            <!-- Order Status -->
            <div class="section">
                <div class="section-title">Status</div>
                <div class="info-row">
                    <span class="info-label">Order Status</span>
                    <span class="info-value">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status</span>
                    <span class="info-value">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="section">
                <div class="section-title">Items</div>
                <div class="order-items">
                    @if(isset($orderData['cart_details']['items']) && count($orderData['cart_details']['items']) > 0)
                        @foreach($orderData['cart_details']['items'] as $item)
                            <div class="order-item">
                                <div class="item-details">
                                    <div class="item-name">{{ $item['title'] }}</div>
                                    <div class="item-meta">{{ $item['quantity'] }} × ${{ number_format($item['price']['price'], 2) }}</div>
                                </div>
                                <div class="item-price">${{ number_format($item['quantity'] * $item['price']['price'], 2) }}</div>
                            </div>
                        @endforeach
                    @else
                        <p style="color: #666666; padding: 10px 0; font-size: 14px;">No items found.</p>
                    @endif
                </div>
            </div>

            <!-- Order Summary -->
            <div class="section">
                <div class="section-title">Summary</div>
                <div class="summary-row">
                    <span class="summary-label">Items</span>
                    <span class="summary-value">${{ number_format($orderData['cart_details']['items_price'] ?? 0, 2) }}</span>
                </div>
                @if(isset($orderData['cart_details']['discount']['amount']) && $orderData['cart_details']['discount']['amount'] > 0)
                    <div class="summary-row">
                        <span class="summary-label">Discount ({{ $orderData['cart_details']['discount']['coupon'] }})</span>
                        <span class="summary-value">-${{ number_format($orderData['cart_details']['discount']['amount'], 2) }}</span>
                    </div>
                @endif
                <div class="summary-row">
                    <span class="summary-label">Tax</span>
                    <span class="summary-value">${{ number_format($orderData['cart_details']['charges']['tax_price'] ?? 0, 2) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Delivery</span>
                    <span class="summary-value">${{ number_format($orderData['cart_details']['charges']['delivery_charges'] ?? 0, 2) }}</span>
                </div>
                <div class="summary-row total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">${{ number_format($orderData['payable_amount'] ?? 0, 2) }}</span>
                </div>
            </div>

            <!-- Track Order Button -->
            @if(isset($trackOrderUrl) && $trackOrderUrl !== '#')
                <div class="track-button-container">
                    <a href="{{ $trackOrderUrl }}" class="track-button">Track Order</a>
                </div>
            @endif

            <div class="divider"></div>

            <!-- Delivery Address -->
            <div class="section address-section">
                <div class="section-title">Delivery Address</div>
                <div class="address-text">
                    {{ $order->street_address }}<br>
                    {{ $order->state }}, {{ $order->zip_code }}<br>
                    @if($order->city)
                        {{ $order->city->name }}
                    @endif
                </div>
                <div class="info-row" style="border: none; padding: 0;">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{{ $order->phone }}</span>
                </div>
                @if($order->notes)
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                        <div style="font-size: 13px; color: #666666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Notes</div>
                        <p style="color: #000000; margin: 0; font-size: 14px; line-height: 1.6;">{{ $order->notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>If you have any questions, please contact our support team.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'Your Company') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
