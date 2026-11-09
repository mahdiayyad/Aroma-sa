<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('emails.review_request.title') }}</title>
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
        .product {
            background-color: #fff3e2;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
        }
        .product-name {
            font-weight: bold;
            margin: 0 0 10px 0;
        }
        .button {
            display: inline-block;
            background-color: #704F2F;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
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

        <h2>{{ __('emails.review_request.greeting') }}</h2>
        <p>{{ __('emails.review_request.message') }}</p>

        @foreach ($products as $product)
            <div class="product">
                <p class="product-name">{{ $product->name }}</p>
                <a href="{{ route('product.show', [app()->getLocale(), $product->slug]) }}#reviews" class="button">{{ __('emails.review_request.cta') }}</a>
            </div>
        @endforeach

        <p>{{ __('emails.review_request.thank_you') }}</p>

        <div class="footer">
            <p>{{ __('emails.footer.contact') }}: {{ config('aroma.contact.email') }}</p>
            <p>© {{ now()->year }} {{ $brand['name'] ?? 'Aroma' }}. {{ __('emails.footer.all_rights') }}</p>
        </div>
    </div>
</body>
</html>
