@extends('layouts.app')

@section('title', ($post->translate('meta_title') ?: $post->title).' — '.$brand['name'])
@section('meta_description', $post->translate('meta_description') ?: $post->summary())

@push('head')
    <link href="{{ \App\Support\Assets::versioned('css/components/blog.css') }}" rel="stylesheet">
@endpush

@section('content')
@php($locale = app()->getLocale())
<div class="container my-5">
    <article class="aroma-blog-article">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('home', $locale) }}">{{ __('storefront.nav.home') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('blog.index', $locale) }}">{{ __('storefront.blog.title') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $post->title }}</li>
            </ol>
        </nav>

        <header class="mb-4">
            <h1 class="aroma-section-title">{{ $post->title }}</h1>
            <time class="aroma-blog-date" datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j M Y') }}</time>
        </header>

        @if ($post->coverUrl())
            <img src="{{ $post->coverUrl() }}" alt="" class="aroma-blog-article-cover">
        @endif

        <div class="aroma-blog-body">
            @foreach ($post->blocks() as $block)
                @if ($block['type'] === 'heading')
                    <h2>{{ $block['text'] }}</h2>
                @else
                    <p>{{ $block['text'] }}</p>
                @endif
            @endforeach
        </div>

        <div class="d-flex gap-3 justify-content-center flex-wrap mt-5">
            <a href="{{ route('blog.index', $locale) }}" class="btn btn-aroma-outline">{{ __('storefront.blog.all') }}</a>
            <a href="{{ route('home', $locale) }}#categories" class="btn btn-aroma">{{ __('storefront.hero.cta') }}</a>
        </div>
    </article>
</div>

@push('structured_data')
<script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post->title,
        'datePublished' => $post->published_at->toAtomString(),
        'dateModified' => $post->updated_at->toAtomString(),
        'image' => $post->coverUrl(),
        'publisher' => ['@type' => 'Organization', 'name' => $brand['name']],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endpush
@endsection
