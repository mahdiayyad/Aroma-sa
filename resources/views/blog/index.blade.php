@extends('layouts.app')

@section('title', __('storefront.blog.title').' — '.$brand['name'])
@section('meta_description', __('storefront.blog.subtitle'))

@push('head')
    <link href="{{ \App\Support\Assets::versioned('css/components/blog.css') }}" rel="stylesheet">
@endpush

@section('content')
@php($locale = app()->getLocale())
<div class="container my-5">
    <div class="text-center mb-5">
        <span class="aroma-eyebrow d-block mb-2">{{ $brand['name'] }}</span>
        <h1 class="aroma-section-title d-inline-block">{{ __('storefront.blog.title') }}</h1>
        <p class="text-aroma-muted mb-0">{{ __('storefront.blog.subtitle') }}</p>
    </div>

    <div class="row g-4">
        @foreach ($posts as $post)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('blog.show', [$locale, $post->slug]) }}" class="aroma-card aroma-blog-card">
                    @if ($post->coverUrl())
                        <img src="{{ $post->coverUrl() }}" alt="" loading="lazy" class="aroma-blog-cover">
                    @endif
                    <div class="p-4">
                        <time class="aroma-blog-date" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j M Y') }}</time>
                        <h2 class="h5 mb-2">{{ $post->title }}</h2>
                        <p class="text-aroma-muted small mb-0">{{ $post->summary() }}</p>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $posts->links() }}</div>
</div>
@endsection
