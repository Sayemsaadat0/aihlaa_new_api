<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .order-info {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid #4CAF50;
        }
        .order-details {
            margin: 20px 0;
        }
        .order-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .order-item:last-child {
            border-bottom: none;
        }
        .total {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }
        .address {
            background-color: white;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Order Confirmation</h1>
        <p>Thank you for your order!</p>
    </div>
    
    <div class="content">
        <div class="order-info">
            <h2>Order #{{ $order->id }}</h2>
            <p><strong>Status:</strong> {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('F d, Y h:i A') }}</p>
        </div>

        <div class="order-details">
            <h3>Order Items</h3>
            @if(isset($orderData['cart_details']['items']))
                @foreach($orderData['cart_details']['items'] as $item)
                    <div class="order-item">
                        <strong>{{ $item['title'] }}</strong><br>
                        Quantity: {{ $item['quantity'] }} × ${{ number_format($item['price']['price'], 2) }} = ${{ number_format($item['quantity'] * $item['price']['price'], 2) }}
                    </div>
                @endforeach
            @endif
        </div>

        <div class="order-details">
            <h3>Order Summary</h3>
            <p>Items Price: ${{ number_format($orderData['cart_details']['items_price'] ?? 0, 2) }}</p>
            @if(isset($orderData['cart_details']['discount']['amount']) && $orderData['cart_details']['discount']['amount'] > 0)
                <p>Discount ({{ $orderData['cart_details']['discount']['coupon'] }}): -${{ number_format($orderData['cart_details']['discount']['amount'], 2) }}</p>
            @endif
            <p>Tax: ${{ number_format($orderData['cart_details']['charges']['tax_price'] ?? 0, 2) }}</p>
            <p>Delivery Charges: ${{ number_format($orderData['cart_details']['charges']['delivery_charges'] ?? 0, 2) }}</p>
            <div class="total">
                Total Amount: ${{ number_format($orderData['payable_amount'] ?? 0, 2) }}
            </div>
        </div>

        <div class="address">
            <h3>Delivery Address</h3>
            <p>
                {{ $order->street_address }}<br>
                {{ $order->state }}, {{ $order->zip_code }}<br>
                @if($order->city)
                    {{ $order->city->name }}
                @endif
            </p>
            <p><strong>Phone:</strong> {{ $order->phone }}</p>
            @if($order->notes)
                <p><strong>Delivery Notes:</strong> {{ $order->notes }}</p>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>If you have any questions, please contact our support team.</p>
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
    </div>
</body>
</html>

