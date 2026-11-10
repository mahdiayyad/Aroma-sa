{{-- In <body>, not <head> with the scripts: an <img>/<iframe> inside <noscript> in <head> makes no-JS browsers end the head early. --}}
@php($noscriptIds = \App\Support\MarketingPixels::ids())
@if ($noscriptIds['gtm'])
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $noscriptIds['gtm'] }}"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif
@if ($noscriptIds['meta'])
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ $noscriptIds['meta'] }}&ev=PageView&noscript=1"></noscript>
@endif
