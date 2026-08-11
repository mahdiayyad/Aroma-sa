<!DOCTYPE html>
<html dir="ltr" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New contact form message</title>
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
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .meta-table th {
            text-align: left;
            width: 140px;
            padding: 8px 10px;
            color: #704F2F;
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
            <p>New message from the Contact Us form</p>
        </div>

        <table class="meta-table">
            <tr><th>Subject</th><td>{{ __('contact.form.topics.'.$data['topic']) }}</td></tr>
            <tr><th>Name</th><td>{{ $data['name'] }}</td></tr>
            <tr><th>Email</th><td><a href="mailto:{{ $data['email'] }}">{{ $data['email'] }}</a></td></tr>
            @if (!empty($data['phone']))
                <tr><th>Phone</th><td>{{ $data['phone'] }}</td></tr>
            @endif
        </table>

        <h2>Message</h2>
        <div class="message-box">{{ $data['message'] }}</div>

        <div class="footer">
            <p>Sent from the Contact Us form at {{ config('app.url') }} — reply directly to this email to respond to {{ $data['name'] }}.</p>
        </div>
    </div>
</body>
</html>
