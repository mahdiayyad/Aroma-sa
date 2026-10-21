<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('receipts.email.subject', ['order_number' => $order->order_number]) }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; background-color: #f5f5f5; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; }
        .header { text-align: center; border-bottom: 2px solid #704F2F; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #704F2F; }
        .order-info { background-color: #fff3e2; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .order-info p { margin: 5px 0; }
        .footer { margin-top: 30px; font-size: 13px; color: #777; }
    </style>
</head>
<body>
<div class="email-container">
    <div class="header">
        <div class="logo">{{ $brand['name'] ?? 'Aroma' }}</div>
    </div>

    <p>{{ __('receipts.email.greeting', ['name' => $order->customer_name]) }}</p>
    <p>{{ __('receipts.email.body', ['order_number' => $order->order_number]) }}</p>

    <div class="order-info">
        <p><strong>{{ __('receipts.receipt_number', ['number' => $receipt->receipt_number]) }}</strong></p>
        <p>{{ __('receipts.email.amount', ['amount' => \App\Support\Formatting\Money::format($order->total_amount)]) }}</p>
    </div>

    <p>{{ __('receipts.email.questions', ['email' => config('aroma.contact.email')]) }}</p>

    <div class="footer">
        <p>{{ __('receipts.email.signoff', ['brand' => $brand['name'] ?? 'Aroma']) }}</p>
    </div>
</div>
</body>
</html>
