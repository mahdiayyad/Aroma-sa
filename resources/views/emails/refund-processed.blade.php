<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.refund_processed.title') }}</title>
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
        .alert-box {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }
        .notice-box {
            background-color: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 15px;
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

        <h2>{{ __('emails.refund_processed.greeting') }}</h2>

        <div class="alert-box">
            <h3 style="margin: 0; color: #4caf50;">✓ {{ __('emails.refund_processed.status') }}</h3>
            <p style="margin: 5px 0 0 0;">{{ __('emails.refund_processed.message') }}</p>
        </div>

        <p>{{ __('emails.refund_processed.order_details') }}</p>
        <ul>
            <li><strong>{{ __('checkout.order_number') }}:</strong> {{ $order->order_number }}</li>
            <li><strong>{{ __('emails.refund_processed.amount') }}:</strong> {{ number_format($refundAmount, 2) }} SAR</li>
        </ul>

        @if ($shippingDeducted)
            <div class="notice-box">
                <p style="margin: 0;">{{ __('refund.email.shipping_deducted_notice') }}</p>
            </div>
        @endif

        <center>
            <a href="{{ route('order.show', $order) }}" class="button">{{ __('emails.refund_processed.view_order') }}</a>
        </center>

        <p>{{ __('emails.refund_processed.support') }}</p>

        <div class="footer">
            <p>{{ __('emails.footer.contact') }}: {{ config('aroma.contact.email') }}</p>
            <p>{{ __('emails.footer.whatsapp') }}: {{ config('aroma.contact.whatsapp') }}</p>
            <p>© {{ now()->year }} {{ $brand['name'] ?? 'Aroma' }}. {{ __('emails.footer.all_rights') }}</p>
        </div>
    </div>
</body>
</html>
