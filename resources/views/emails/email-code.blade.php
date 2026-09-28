<!DOCTYPE html>
<html dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('email_auth.mail.subject.'.$purposeKey) }}</title>
    <style>
        body { font-family: Arial, Tahoma, sans-serif; color: #241817; line-height: 1.7; background-color: #faf7f2; margin: 0; padding: 24px 12px; }
        .email-container { max-width: 480px; margin: 0 auto; background: #ffffff; padding: 28px 24px; border-radius: 12px; }
        .header { text-align: center; border-bottom: 2px solid #330101; padding-bottom: 16px; margin-bottom: 24px; }
        .logo { font-size: 26px; font-weight: bold; color: #330101; }
        .code { direction: ltr; text-align: center; font-size: 34px; font-weight: bold; letter-spacing: 10px; color: #330101; background: #f4ece1; border-radius: 10px; padding: 14px 8px; margin: 22px 0; }
        .muted { color: #6b5a58; font-size: 13px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">{{ $brand['name'] ?? 'Aroma' }}</div>
        </div>

        <p>{{ __('email_auth.mail.intro.'.$purposeKey) }}</p>

        <div class="code">{{ $code }}</div>

        <p>{{ __('email_auth.mail.expires', ['minutes' => $minutes]) }}</p>
        <p class="muted">{{ __('email_auth.mail.ignore.'.$purposeKey) }}</p>
    </div>
</body>
</html>
