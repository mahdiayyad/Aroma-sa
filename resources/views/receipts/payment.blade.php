{{--
    Payment receipt PDF (rendered by mPDF — see ReceiptPdfRenderer). Layout
    follows the agreed receipt mockup: merchant block, ORDER # bar, shipping +
    items/totals, PAYMENT INFORMATION bar, payment + billing, totals, policy.
    Tables only: mPDF has no flexbox/grid. $start/$end mirror for Arabic.
--}}
@php
    $locale = app()->getLocale();
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    // English receipts use "SAR" (as the confirmation email does): mixing the
    // Arabic symbol into an LTR amount lets bidi reordering scramble it.
    $money = function ($amount) use ($locale) {
        if ($locale !== 'ar') {
            return number_format((float) $amount, 2).' SAR';
        }
        // Arabic fonts draw "-" as a low stroke: use a real minus sign, set
        // (with the digits) in the Latin font.
        $sign = (float) $amount < 0 ? "\u{2212}" : '';

        return new \Illuminate\Support\HtmlString('<span lang="en">'.$sign.e(number_format(abs((float) $amount), 2)).'</span> '
            .e((string) config('aroma.currency.symbol', 'SAR')));
    };
    $date = function ($value) use ($locale) {
        return $value ? $value->copy()->timezone(config('app.timezone'))->locale($locale)->translatedFormat('j F Y') : '';
    };

    $addressLines = function ($address): array {
        $a = (array) $address;
        $street = trim(implode(' ', array_filter([
            $a['street_address'] ?? null,
            ! empty($a['building_number']) ? '#'.$a['building_number'] : null,
            ! empty($a['apartment_number']) ? '/ '.$a['apartment_number'] : null,
        ])));
        $country = strtoupper((string) ($a['country'] ?? 'SA'));

        return array_values(array_filter([
            $a['recipient_name'] ?? null,
            $street !== '' ? $street : ($a['formatted_address'] ?? null),
            $street !== '' ? ($a['district'] ?? null) : null,
            trim(implode(' ', array_filter([$a['city'] ?? null, $a['postal_code'] ?? null]))),
            $country === 'SA' ? __('location.manual.country_sa') : $country,
        ], function ($line) { return trim((string) $line) !== ''; }));
    };

    $units = (int) $order->items->sum('quantity');
    $giftParts = array_filter([
        (float) $order->gift_wrap_fee > 0 ? __('receipts.gift_wrap') : null,
        ! empty($order->greeting_card_id) ? __('receipts.greeting_card') : null,
    ]);
    $giftFees = (float) $order->gift_wrap_fee + (float) $order->greeting_card_fee;
    $totalBeforeTax = (float) $order->total_amount - (float) $order->tax_amount;
    $paidAt = $receipt->created_at;
    $website = parse_url((string) config('app.url'), PHP_URL_HOST) ?: (string) config('app.url');
    $storeAddress = (string) config('aroma.receipts.store_address');
    $vatNumber = (string) config('aroma.receipts.vat_number');
    $contactEmail = (string) config('aroma.contact.email');
    $contactPhone = (string) config('aroma.contact.phone');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    /* Brand colours (public/css tokens): brown #330101, wine #7A2E3D, gold #C9B39A, text #241817. */
    body { font-family: dejavusans; font-size: 8.5pt; color: #241817; }
    .frame { border: 0.8pt solid #C9B39A; border-top: 3pt solid #330101; padding: 16pt 14pt 12pt 14pt; }
    .merchant { font-size: 12.5pt; font-weight: bold; color: #330101; }
    .heading { font-size: 10pt; font-weight: bold; color: #7A2E3D; }
    .muted { color: #5c4a45; line-height: 1.45; }
    .bar { background-color: #330101; color: #ffffff; font-weight: bold; font-size: 12pt; padding: 4pt 7pt; margin-top: 8pt; }
    table.box { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    td.cell { vertical-align: top; padding: 7pt 8pt; border: 0.6pt solid #C9B39A; }
    td.cell-totals { background-color: #faf6f1; }
    .label { font-weight: bold; color: #7A2E3D; }
    table.items { width: 100%; border-collapse: collapse; }
    table.items td { padding: 2pt 0; vertical-align: top; }
    table.totals { width: 100%; border-collapse: collapse; }
    table.totals td { padding: 1.4pt 0; }
    .dash { color: #C9B39A; letter-spacing: 1pt; }
    .strong { font-weight: bold; }
    .grand { font-weight: bold; color: #330101; }
    .small { font-size: 7.5pt; color: #5c4a45; }
    .footer { margin-top: 8pt; font-size: 8.5pt; line-height: 1.5; }
    .footer .strong { color: #330101; }
</style>
</head>
<body>
<div class="frame">

    {{-- Merchant name & location, transaction date, online address --}}
    <table width="100%" style="border-collapse: collapse;">
        <tr>
            <td style="vertical-align: top; text-align: {{ $start }};">
                <div class="merchant">{{ $brand['name'] ?? 'Aroma' }}</div>
                <div class="muted">
                    @if ($storeAddress !== '')
                        {{ $storeAddress }}<br>
                    @endif
                    {{ __('receipts.order_placed', ['date' => $date($order->created_at)]) }}<br>
                    {{ $website }}
                    @if ($vatNumber !== '')
                        <br>{{ __('receipts.vat_number', ['number' => $vatNumber]) }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top; text-align: {{ $end }};">
                <div class="heading">{{ __('receipts.heading') }}</div>
                <div class="muted">
                    {{ __('receipts.receipt_number', ['number' => $receipt->receipt_number]) }}<br>
                    {{ __('receipts.paid_on', ['date' => $date($paidAt)]) }}
                </div>
            </td>
        </tr>
    </table>

    <div class="bar">{{ __('receipts.order_number', ['number' => $order->order_number]) }}</div>

    {{-- Shipping address + items & shipment totals --}}
    <table class="box">
        <tr>
            <td class="cell" rowspan="2" style="width: 38%;">
                <div class="label">{{ __('receipts.shipping_address') }}</div>
                @foreach ($addressLines($order->shipping_address) as $line)
                    {{ $line }}<br>
                @endforeach
                <br>
                <div class="label">{{ __('receipts.shipping') }}</div>
                {{ in_array($order->shipping_method, [null, '', 'standard'], true) ? __('receipts.shipping_standard') : ucfirst((string) $order->shipping_method) }}
            </td>
            <td class="cell" style="width: 62%;">
                <table class="items">
                    <tr>
                        <td class="strong" style="text-align: {{ $start }};">{{ __('receipts.items_ordered') }}</td>
                        <td class="strong" style="text-align: {{ $end }}; width: 28%;">{{ __('receipts.price') }}</td>
                    </tr>
                    @foreach ($order->items as $item)
                        <tr>
                            <td style="text-align: {{ $start }}; padding-top: 6pt;">
                                <span class="strong">{{ $item->quantity }}&nbsp;&nbsp;{{ $item->product_data['name'] ?? '' }}</span>
                                @if ($item->variantLabel())
                                    ({{ $item->variantLabel() }})
                                @endif
                                @foreach ($item->optionLines() as $label => $value)
                                    <br><span class="small">{{ $label }}: {{ $value }}</span>
                                @endforeach
                            </td>
                            <td style="text-align: {{ $end }}; padding-top: 6pt;">{{ $money($item->line_total) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="2" style="text-align: {{ $start }}; padding-top: 8pt;">
                            <span lang="en">-</span> {{ __('receipts.items_count', ['count' => $units]) }}
                            {{ __('receipts.gift_options', ['options' => $giftParts ? implode(', ', $giftParts) : __('receipts.gift_none')]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="cell cell-totals">
                <table class="totals">
                    <tr><td style="text-align: {{ $end }};">{{ __('receipts.items_subtotal') }}</td><td style="text-align: {{ $end }}; width: 28%;">{{ $money($order->subtotal) }}</td></tr>
                    @if ((float) $order->discount_amount > 0)
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.discount') }}</td><td style="text-align: {{ $end }};">{{ $money(-1 * (float) $order->discount_amount) }}</td></tr>
                    @endif
                    <tr><td style="text-align: {{ $end }};">{{ __('receipts.shipping_handling') }}</td><td style="text-align: {{ $end }};">{{ $money($order->shipping_cost) }}</td></tr>
                    @if ($giftFees > 0)
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.gift_fees') }}</td><td style="text-align: {{ $end }};">{{ $money($giftFees) }}</td></tr>
                    @endif
                    <tr><td></td><td class="dash" lang="en" style="text-align: {{ $end }};">- - - -</td></tr>
                    <tr><td style="text-align: {{ $end }};">{{ __('receipts.subtotal') }}</td><td style="text-align: {{ $end }};">{{ $money($totalBeforeTax) }}</td></tr>
                    <tr><td></td><td class="dash" lang="en" style="text-align: {{ $end }};">- - - -</td></tr>
                    <tr><td class="grand" style="text-align: {{ $end }};">{{ __('receipts.total_shipment') }}</td><td class="grand" style="text-align: {{ $end }};">{{ $money($order->total_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="bar">{{ __('receipts.payment_information') }}</div>

    {{-- Payment method, authorization, billing address + transaction totals --}}
    <table class="box">
        <tr>
            <td class="cell" style="width: 38%;">
                <div class="label">{{ __('receipts.payment_method') }}</div>
                {{ $payment->methodLabel }}@if ($payment->maskedCard()): {{ $payment->maskedCard() }}@endif<br>
                @if ($payment->authorizationCode)
                    {{ __('receipts.authorization_code', ['code' => $payment->authorizationCode]) }}<br>
                @endif
                @if ($payment->transactionReference)
                    {{ __('receipts.transaction_reference', ['reference' => $payment->transactionReference]) }}<br>
                @endif
                {{ __('receipts.transaction_type') }}
                <br><br>
                <div class="label">{{ __('receipts.billing_address') }}</div>
                @foreach ($addressLines($order->billing_address) as $line)
                    {{ $line }}<br>
                @endforeach
            </td>
            <td class="cell cell-totals" style="width: 62%;">
                <table class="totals">
                    <tr><td style="text-align: {{ $end }};">{{ __('receipts.items_subtotal') }}</td><td style="text-align: {{ $end }}; width: 28%;">{{ $money($order->subtotal) }}</td></tr>
                    @if ((float) $order->discount_amount > 0)
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.discount') }}</td><td style="text-align: {{ $end }};">{{ $money(-1 * (float) $order->discount_amount) }}</td></tr>
                    @endif
                    <tr><td style="text-align: {{ $end }};">{{ __('receipts.shipping_handling') }}</td><td style="text-align: {{ $end }};">{{ $money($order->shipping_cost) }}</td></tr>
                    @if ($giftFees > 0)
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.gift_fees') }}</td><td style="text-align: {{ $end }};">{{ $money($giftFees) }}</td></tr>
                    @endif
                    {{-- Only when tax is actually recorded: "VAT 0.00" on VAT-inclusive
                         prices would be a false statement on a customer document. --}}
                    @if ((float) $order->tax_amount > 0)
                        <tr><td></td><td class="dash" lang="en" style="text-align: {{ $end }};">- - - -</td></tr>
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.total_before_tax') }}</td><td style="text-align: {{ $end }};">{{ $money($totalBeforeTax) }}</td></tr>
                        <tr><td style="text-align: {{ $end }};">{{ __('receipts.tax') }}</td><td style="text-align: {{ $end }};">{{ $money($order->tax_amount) }}</td></tr>
                    @endif
                    <tr><td></td><td class="dash" lang="en" style="text-align: {{ $end }};">- - - -</td></tr>
                    <tr><td class="grand" style="text-align: {{ $end }};">{{ __('receipts.grand_total') }}</td><td class="grand" style="text-align: {{ $end }};">{{ $money($order->total_amount) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Refund / return policy and contact --}}
    <div class="footer">
        <span class="strong">{{ __('receipts.returns', ['url' => route('guides.returns')]) }}</span><br>
        <span class="strong">{{ __('receipts.questions') }}</span>
        {{ $contactEmail }}@if ($contactPhone !== '') · {{ $contactPhone }}@endif
    </div>
</div>
</body>
</html>
