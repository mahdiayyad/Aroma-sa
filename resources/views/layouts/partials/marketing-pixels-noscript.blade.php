{{-- In <body>, not <head> with the script: an <img> inside <noscript> in <head> makes no-JS browsers end the head early. --}}
@php($metaPixelId = \App\Support\MarketingPixels::ids()['meta'])
@if ($metaPixelId)
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ $metaPixelId }}&ev=PageView&noscript=1"></noscript>
@endif
