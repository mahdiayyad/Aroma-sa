@props(['name' => 'otp_code', 'length' => 6])

<div class="aroma-otp-boxes" dir="ltr" data-otp-length="{{ $length }}">
    @for ($i = 0; $i < $length; $i++)
        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
               class="aroma-otp-box" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
               aria-label="{{ __('otp.verify') }} {{ $i + 1 }}">
    @endfor
    <input type="hidden" name="{{ $name }}" class="js-otp-value">
</div>
