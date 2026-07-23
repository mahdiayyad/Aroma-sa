<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.order_shipped.title') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #704F2F;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #704F2F;
        }
        h2 {
            color: #704F2F;
            margin: 20px 0 10px 0;
        }
        .status-box {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }
        .tracking-info {
            background-color: #fff3e2;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            background-color: #704F2F;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ $brand['name'] ?? 'Aroma' }}</div>
            <p>{{ $brand['tagline'] ?? 'Awaken your Senses' }}</p>
        </div>

        <h2>{{ __('emails.order_shipped.greeting') }}</h2>

        <div class="status-box">
            <h3 style="margin: 0; color: #4caf50;">✓ {{ __('emails.order_shipped.status') }}</h3>
            <p style="margin: 5px 0 0 0;">{{ __('emails.order_shipped.message') }}</p>
        </div>

        <div class="tracking-info">
            <p><strong>{{ __('checkout.order_number') }}:</strong> {{ $order->order_number }}</p>
            @if($order->tracking_number)
                <p><strong>{{ __('orders.tracking_number') }}:</strong> <code>{{ $order->tracking_number }}</code></p>
            @endif
        </div>

        <h3>{{ __('emails.order_shipped.shipping_address') }}</h3>
        <p>
            {{ $order->shipping_address['recipient_name'] }}<br>
            {{ $order->shipping_address['street_address'] }}<br>
            {{ $order->shipping_address['city'] }}, {{ $order->shipping_address['region'] }}<br>
            @if($order->shipping_address['postal_code'])
                {{ $order->shipping_address['postal_code'] }}<br>
            @endif
            {{ $order->customer_phone }}
        </p>

        <p>{{ __('emails.order_shipped.tracking_info') }}</p>

        <center>
            <a href="{{ route('order.show', $order) }}" class="button">{{ __('emails.order_shipped.view_order') }}</a>
        </center>

        <p>{{ __('emails.order_shipped.thank_you') }}</p>

        <div class="footer">
            <p>{{ __('emails.footer.contact') }}: {{ config('aroma.contact.email') }}</p>
            <p>© {{ now()->year }} {{ $brand['name'] ?? 'Aroma' }}. {{ __('emails.footer.all_rights') }}</p>
        </div>
    </div>
</body>
</html>
