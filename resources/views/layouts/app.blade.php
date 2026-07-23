<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" dir="{{ $direction ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $brand['name'].' — '.$brand['tagline'])</title>
    <meta name="description" content="@yield('meta_description', $brand['tagline'])">

    {{-- Bootstrap 5.3 (RTL build for Arabic) served from CDN so the app renders
         without a Node build step. The production SCSS pipeline emits the same
         into public/css when compiled. --}}
    @if (($direction ?? 'ltr') === 'rtl')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/aroma.css') }}" rel="stylesheet">

    @stack('head')
</head>
<body>
    @include('layouts.partials.header')

    <main>
        @if (session('status') || $errors->any())
            <div class="container mt-3">@include('partials.flash')</div>
        @endif
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
