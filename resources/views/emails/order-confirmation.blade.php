<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.order_confirmation.title') }}</title>
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
        .order-info {
            background-color: #fff3e2;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .order-info p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th {
            background-color: #704F2F;
            color: white;
            padding: 10px;
            text-align: left;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .total-row {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
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
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ $brand['name'] ?? 'Aroma' }}</div>
            <p>{{ $brand['tagline'] ?? 'Awaken your Senses' }}</p>
        </div>

        <h2>{{ __('emails.order_confirmation.greeting', ['name' => $order->customer_name]) }}</h2>

        <p>{{ __('emails.order_confirmation.message') }}</p>

        <div class="order-info">
            <p><strong>{{ __('checkout.order_number') }}:</strong> {{ $order->order_number }}</p>
            <p><strong>{{ __('checkout.order_date') }}:</strong> {{ $order->created_at->translatedFormat('j M Y, g:i A') }}</p>
            <p><strong>{{ __('checkout.total') }}:</strong> {{ number_format($order->total_amount, 2) }} SAR</p>
        </div>

        <h3>{{ __('emails.order_confirmation.order_details') }}</h3>

        <table>
            <thead>
                <tr>
                    <th>{{ __('cart.product') }}</th>
                    <th>{{ __('cart.price') }}</th>
                    <th>{{ __('cart.quantity') }}</th>
                    <th>{{ __('cart.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->product_data['name'] ?? 'Product' }}
                            @if($item->variant_data)
                                <br><small>{{ $item->variant_data['name'] ?? '' }}</small>
                            @endif
                        </td>
                        <td>{{ number_format($item->unit_price, 2) }} SAR</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->line_total, 2) }} SAR</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">{{ __('checkout.total') }}</td>
                    <td>{{ number_format($order->total_amount, 2) }} SAR</td>
                </tr>
            </tbody>
        </table>

        <h3>{{ __('emails.order_confirmation.shipping_address') }}</h3>
        {{-- location_code present = resolved via the Saudi National Address
             lookup at checkout; coordinates-only (no code, no street_address)
             = pinned via the map picker instead; neither = this order
             predates the cutover and still carries the old free-text fields. --}}
        <p>
            <strong>{{ $order->shipping_address['recipient_name'] ?? '' }}</strong><br>
            @if(!empty($order->shipping_address['location_code']))
                {{ $order->shipping_address['formatted_address'] ?? '' }}<br>
                @if(!empty($order->shipping_address['district'])){{ $order->shipping_address['district'] }}, @endif{{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['region'] ?? '' }}<br>
            @elseif(!empty($order->shipping_address['latitude']) && !empty($order->shipping_address['longitude']))
                <a href="https://www.google.com/maps?q={{ $order->shipping_address['latitude'] }},{{ $order->shipping_address['longitude'] }}">{{ __('location.map.pinned_label') }}</a><br>
            @else
                {{ $order->shipping_address['street_address'] ?? '' }}<br>
                {{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['region'] ?? '' }}<br>
                @if($order->shipping_address['postal_code'] ?? null)
                    {{ $order->shipping_address['postal_code'] }}<br>
                @endif
            @endif
            {{ $order->shipping_address['phone'] ?? $order->customer_phone }}
        </p>

        <center>
            <a href="{{ route('order.show', $order) }}" class="button">{{ __('emails.order_confirmation.view_order') }}</a>
        </center>

        <p>{{ __('emails.order_confirmation.thank_you') }}</p>

        <div class="footer">
            <p>{{ __('emails.footer.contact') }}: {{ config('aroma.contact.email') }}</p>
            <p>{{ __('emails.footer.whatsapp') }}: {{ config('aroma.contact.whatsapp') }}</p>
            <p>© {{ now()->year }} {{ $brand['name'] ?? 'Aroma' }}. {{ __('emails.footer.all_rights') }}</p>
        </div>
    </div>
</body>
</html>
