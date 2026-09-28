<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New refund request</title>
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
            border-bottom: 2px solid #330101;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #330101;
        }
        h2 {
            color: #330101;
            margin: 20px 0 10px 0;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .meta-table th {
            text-align: left;
            width: 140px;
            padding: 8px 10px;
            color: #330101;
            vertical-align: top;
        }
        .meta-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        .message-box {
            background-color: #fff3e2;
            padding: 15px 20px;
            border-radius: 5px;
            margin: 20px 0;
            white-space: pre-line;
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
            <p>New refund request submitted</p>
        </div>

        <table class="meta-table">
            <tr><th>Order</th><td>{{ $order->order_number }}</td></tr>
            <tr><th>Customer</th><td>{{ $order->customer_name }}</td></tr>
            <tr><th>Email</th><td><a href="mailto:{{ $order->customer_email }}">{{ $order->customer_email }}</a></td></tr>
            <tr><th>Reason</th><td>{{ __('refund.reasons.'.$refundRequest->reason) }}</td></tr>
        </table>

        @if (! empty($refundRequest->notes))
            <h2>Additional notes</h2>
            <div class="message-box">{{ $refundRequest->notes }}</div>
        @endif

        <div class="footer">
            <p>Review this request in the admin panel to approve or reject it.</p>
        </div>
    </div>
</body>
</html>
