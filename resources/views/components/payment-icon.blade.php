@props(['method'])

{{--
    Real payment-network marks in their own brand colors — not the site's
    emerald/gold accent, and not generic credit-card glyphs. Card networks
    (mada, Visa, Mastercard, Apple Pay) are shown as their actual logo in
    its actual color, sitting on the existing white badge card. BNPL
    providers (tabby, Tamara) are shown the way they actually brand
    themselves in the wild — a gradient-color chip with their wordmark in
    dark bold type — so this component renders those as a self-contained
    colored pill rather than an icon-plus-separate-label.

    Shapes/colors are sourced from each network's own published assets
    where one was fetchable (Visa/Apple Pay via simple-icons, MIT-licensed;
    Mastercard as its literal red/orange two-circle mark; mada via its
    official SVG on Wikimedia Commons, full two-tone symbol + wordmark).
    tabby and Tamara have no fetchable public vector asset (tabby's brand
    page links only to a Google Drive folder; Tamara's fetched asset was
    wordmark-only with no colour specified) — their gradient stops here are
    matched by eye against reference badge screenshots, not a verified hex
    — everything else on this list is traced from a real source.
--}}
@switch($method)
    @case('visa')
        <svg viewBox="0 0 24 24" fill="#1A1F71" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Visa">
            <path d="M9.112 8.262L5.97 15.758H3.92L2.374 9.775c-.094-.368-.175-.503-.461-.658C1.447 8.864.677 8.627 0 8.479l.046-.217h3.3a.904.904 0 01.894.764l.817 4.338 2.018-5.102zm8.033 5.049c.008-1.979-2.736-2.088-2.717-2.972.006-.269.262-.555.822-.628a3.66 3.66 0 011.913.336l.34-1.59a5.207 5.207 0 00-1.814-.333c-1.917 0-3.266 1.02-3.278 2.479-.012 1.079.963 1.68 1.698 2.04.756.367 1.01.603 1.006.931-.005.504-.602.725-1.16.734-.975.015-1.54-.263-1.992-.473l-.351 1.642c.453.208 1.289.39 2.156.398 2.037 0 3.37-1.006 3.377-2.564m5.061 2.447H24l-1.565-7.496h-1.656a.883.883 0 00-.826.55l-2.909 6.946h2.036l.405-1.12h2.488zm-2.163-2.656l1.02-2.815.588 2.815zm-8.16-4.84l-1.603 7.496H8.34l1.605-7.496z"/>
        </svg>
        @break

    @case('mastercard')
        {{-- The real mark: a red circle and an orange circle, overlapping —
             Mastercard's actual current logo is exactly this, nothing more.
             Plain alpha compositing (not mix-blend-mode, which some SVG
             nesting contexts silently ignore) — red drawn first, orange on
             top at partial opacity so the overlap reads as a blended tone. --}}
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Mastercard">
            <circle cx="9" cy="12" r="7" fill="#EB001B"/>
            <circle cx="15" cy="12" r="7" fill="#F79E1B" fill-opacity="0.8"/>
        </svg>
        @break

    @case('applepay')
        <svg viewBox="0 0 24 24" fill="#000" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Apple Pay">
            <path d="M2.15 4.318a42.16 42.16 0 0 0-.454.003c-.15.005-.303.013-.452.04a1.44 1.44 0 0 0-1.06.772c-.07.138-.114.278-.14.43-.028.148-.037.3-.04.45A10.2 10.2 0 0 0 0 6.222v11.557c0 .07.002.138.003.207.004.15.013.303.04.452.027.15.072.291.142.429a1.436 1.436 0 0 0 .63.63c.138.07.278.115.43.142.148.027.3.036.45.04l.208.003h20.194l.207-.003c.15-.004.303-.013.452-.04.15-.027.291-.071.428-.141a1.432 1.432 0 0 0 .631-.631c.07-.138.115-.278.141-.43.027-.148.036-.3.04-.45.002-.07.003-.138.003-.208l.001-.246V6.221c0-.07-.002-.138-.004-.207a2.995 2.995 0 0 0-.04-.452 1.446 1.446 0 0 0-1.2-1.201 3.022 3.022 0 0 0-.452-.04 10.448 10.448 0 0 0-.453-.003zm0 .512h19.942c.066 0 .131.002.197.003.115.004.25.01.375.032.109.02.2.05.287.094a.927.927 0 0 1 .407.407.997.997 0 0 1 .094.288c.022.123.028.258.031.374.002.065.003.13.003.197v11.552c0 .065 0 .13-.003.196-.003.115-.009.25-.032.375a.927.927 0 0 1-.5.693 1.002 1.002 0 0 1-.286.094 2.598 2.598 0 0 1-.373.032l-.2.003H1.906c-.066 0-.133-.002-.196-.003a2.61 2.61 0 0 1-.375-.032c-.109-.02-.2-.05-.288-.094a.918.918 0 0 1-.406-.407 1.006 1.006 0 0 1-.094-.288 2.531 2.531 0 0 1-.032-.373 9.588 9.588 0 0 1-.002-.197V6.224c0-.065 0-.131.002-.197.004-.114.01-.248.032-.375.02-.108.05-.199.094-.287a.925.925 0 0 1 .407-.406 1.03 1.03 0 0 1 .287-.094c.125-.022.26-.029.375-.032.065-.002.131-.002.196-.003zm4.71 3.7c-.3.016-.668.199-.88.456-.191.22-.36.58-.316.918.338.03.675-.169.888-.418.205-.258.345-.603.308-.955zm2.207.42v5.493h.852v-1.877h1.18c1.078 0 1.835-.739 1.835-1.812 0-1.07-.742-1.805-1.808-1.805zm.852.719h.982c.739 0 1.161.396 1.161 1.089 0 .692-.422 1.092-1.164 1.092h-.979zm-3.154.3c-.45.01-.83.28-1.05.28-.235 0-.593-.264-.981-.257a1.446 1.446 0 0 0-1.23.747c-.527.908-.139 2.255.374 2.995.249.366.549.769.944.754.373-.014.52-.242.973-.242.454 0 .586.242.98.235.41-.007.667-.366.915-.733.286-.417.403-.82.41-.841-.007-.008-.79-.308-.797-1.209-.008-.754.615-1.113.644-1.135-.352-.52-.9-.578-1.09-.593a1.123 1.123 0 0 0-.092-.002zm8.204.397c-.99 0-1.606.533-1.652 1.256h.777c.072-.358.369-.586.845-.586.502 0 .803.266.803.711v.309l-1.097.064c-.951.054-1.488.484-1.488 1.184 0 .72.548 1.207 1.332 1.207.526 0 1.032-.281 1.264-.727h.019v.659h.788v-2.76c0-.803-.62-1.317-1.591-1.317zm1.94.072l1.446 4.009c0 .003-.073.24-.073.247-.125.41-.33.571-.711.571-.069 0-.206 0-.267-.015v.666c.06.011.267.019.335.019.83 0 1.226-.312 1.568-1.283l1.5-4.214h-.868l-1.012 3.259h-.015l-1.013-3.26zm-1.167 2.189v.316c0 .521-.45.917-1.024.917-.442 0-.731-.228-.731-.579 0-.342.278-.56.769-.593z"/>
        </svg>
        @break

    @case('mada')
        {{-- mada's own published mark (Saudi Payments Network), traced from
             their official SVG on Wikimedia Commons — the full two-tone
             symbol (green + blue) plus the wordmark, in their real colors. --}}
        <svg viewBox="0 320 800 270" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="mada">
            <rect y="473.1" width="336.8" height="112.2" fill="#84B740"/>
            <rect y="320" width="336.8" height="112.3" fill="#259BD6"/>
            <g fill="#27292D">
                <path d="M673.6,562.5l-1.5,0.3c-5.2,1-7.1,1.4-10.9,1.4c-8.8,0-19.2-4.5-19.2-25.7c0-10.9,1.8-25.4,18.2-25.4h0.1c2.8,0.2,6,0.5,12,2.3l1.3,0.4L673.6,562.5L673.6,562.5z M676.3,456.8l-2.7,0.5v39.2l-2.4-0.7l-0.7-0.2c-2.7-0.8-8.9-2.6-14.9-2.6c-32.8,0-39.7,24.8-39.7,45.6c0,28.5,16,44.9,43.9,44.9c11.8,0,20.5-1.2,29.3-4.1c8.1-2.6,11-6.3,11-14.2V452.7C692.3,454.1,684.2,455.5,676.3,456.8"/>
                <path d="M771.1,563.2l-1.4,0.4l-5,1.3c-4.7,1.2-8.9,1.9-12.1,1.9c-7.7,0-12.3-3.8-12.3-10.3c0-4.2,1.9-11.3,14.5-11.3h16.3V563.2z M759.6,492.5c-10.1,0-20.5,1.8-33.4,5.8l-8.4,2.5l2.8,19l8.2-2.7c8.6-2.8,19.3-4.6,27.3-4.6c3.6,0,14.6,0,14.6,11.9v5.2h-15.3c-27.9,0-40.8,8.9-40.8,28c0,16.3,11.9,26.1,31.9,26.1c6.2,0,14.8-1.2,22.2-3l0.4-0.1l0.4,0.1l2.5,0.4c7.8,1.4,15.9,2.8,23.8,4.3V523C795.8,502.8,783.6,492.5,759.6,492.5"/>
                <path d="M576.8,563.2l-1.4,0.4l-5,1.3c-4.7,1.2-8.8,1.9-12.1,1.9c-7.7,0-12.3-3.8-12.3-10.3c0-4.2,1.9-11.3,14.4-11.3h16.3L576.8,563.2L576.8,563.2z M565.4,492.5c-10.2,0-20.5,1.8-33.4,5.8l-8.4,2.5l2.8,19l8.2-2.7c8.6-2.8,19.3-4.6,27.3-4.6c3.6,0,14.6,0,14.6,11.9v5.2h-15.3c-27.9,0-40.9,8.9-40.9,28c0,16.3,11.9,26.1,32,26.1c6.2,0,14.8-1.2,22.2-3l0.4-0.1l0.4,0.1l2.4,0.4c7.9,1.4,15.9,2.8,23.8,4.4v-62.4C601.6,502.7,589.4,492.5,565.4,492.5"/>
                <path d="M471.5,492.7c-12.7,0-23.2,4.2-27.1,6l-1,0.5l-0.9-0.7c-5.4-3.9-13.3-5.9-24.3-5.9c-9.7,0-18.8,1.4-28.7,4.3c-8.5,2.6-11.8,6.7-11.8,14.4v71.3h26.6v-65.9l1.3-0.4c5.4-1.8,8.6-2.1,11.7-2.1c7.7,0,11.6,4.1,11.6,12.1v56.4h26.2v-57.5c0-3.4-0.7-5.4-0.8-5.8l-0.9-1.7l1.8-0.8c4-1.8,8.4-2.7,13-2.7c5.3,0,11.6,2.1,11.6,12.1v56.4h26.1v-59C505.9,502.8,494.7,492.7,471.5,492.7"/>
                <path d="M526.1,424.5h1.2c27.9,0,40.9-9.2,40.9-31.9c0-16.3-11.9-29.3-31.9-29.3h-25.7c-7.7,0-12.3-4.4-12.3-11.8c0-5,1.9-11.2,14.5-11.2H569c1.2-7.3,1.8-11.9,2.9-19.2h-58.4c-27.2,0-40.9,11.4-40.9,30.4c0,18.8,11.9,28.6,31.9,28.6h25.7c7.7,0,12.3,6.1,12.3,12.5c0,4.2-1.9,12.9-14.4,12.9h-4.3l-82.3-0.2l0,0h-15c-12.7,0-21.6-7.2-21.6-23.9v-3.6c0-17.4,6.9-28.2,21.6-28.2h24.4c1.1-7.4,1.8-12.1,2.8-19.1h-30.4h-2.9c-24.9,0-42.1,16.7-42.7,45.8l0,0v1.1v11.9c0.6,29.1,17.8,43,42.7,43h2.9h21.4l44.6,0.1l0,0h26.6L526.1,424.5L526.1,424.5z"/>
            </g>
        </svg>
        @break

    @case('tabby')
        {{-- tabby's real self-branding: a teal-to-green gradient pill with
             the lowercase wordmark in dark, bold type — not white-on-solid.
             Gradient stops are a close match to their reference badge, not
             a verified hex (see this file's top docblock). --}}
        <span class="aroma-payment-chip" style="background:linear-gradient(120deg, #3FD9C7 0%, #7CE87A 100%)">
            <span class="aroma-payment-chip-text" style="color:#0F1A17">tabby</span>
        </span>
        @break

    @case('tamara')
        {{-- Tamara's real self-branding: a multi-stop violet → coral →
             peach → teal gradient pill with the Arabic wordmark in dark,
             bold type. Gradient stops are a close match, not a verified
             hex. --}}
        <span class="aroma-payment-chip" style="background:linear-gradient(90deg, #9B7FE0 0%, #E38FC0 30%, #F3A98A 55%, #F6C98A 72%, #7FC6D6 100%)">
            <span class="aroma-payment-chip-text" dir="rtl" style="color:#1A1A1A">تمارا</span>
        </span>
        @break

    @default
        <i class="bi bi-credit-card"></i>
@endswitch
