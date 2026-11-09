@extends('admin.layouts.master')

@php($editing = $post->exists)
@section('title', ($editing ? __('admin.blog.edit') : __('admin.blog.new')).' — '.__('admin.app_name'))
@section('page_title', $editing ? __('admin.blog.edit') : __('admin.blog.new'))

@section('content')
    <x-admin.page-header :title="$editing ? $post->title : __('admin.blog.new')" :back="route('admin.blog-posts.index')" />

    <form method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.blog-posts.update', $post) : route('admin.blog-posts.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-8">
                <x-admin.card :title="__('admin.blog.article')">
                    <x-admin.form.bilingual name="title" :label="__('admin.blog.article_title')" :translations="$post->getTranslations('title')" required />
                    <x-admin.form.bilingual name="excerpt" :label="__('admin.blog.excerpt')" :translations="$post->getTranslations('excerpt')" type="textarea" :rows="2" />
                    <x-admin.form.bilingual name="body" :label="__('admin.blog.body')" :translations="$post->getTranslations('body')" type="textarea" :rows="16" required />
                    <div class="admin-hint">{{ __('admin.blog.body_hint') }}</div>
                </x-admin.card>

                <x-admin.card :title="__('admin.blog.seo')">
                    <x-admin.form.bilingual name="meta_title" :label="__('admin.blog.meta_title')" :translations="$post->getTranslations('meta_title')" />
                    <x-admin.form.bilingual name="meta_description" :label="__('admin.blog.meta_description')" :translations="$post->getTranslations('meta_description')" type="textarea" :rows="2" />
                </x-admin.card>
            </div>

            <div class="col-lg-4">
                <x-admin.card>
                    <x-admin.form.group :label="__('admin.blog.slug')" name="slug">
                        <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" class="admin-input">
                    </x-admin.form.group>
                    <x-admin.form.group :label="__('admin.blog.cover')" name="cover_image">
                        @if ($post->coverUrl())
                            <img src="{{ $post->coverUrl() }}" alt="" class="admin-thumb mb-2" style="width:64px;height:64px">
                        @endif
                        <input type="file" name="cover_image" accept="image/*" class="admin-input" data-preview="#blogCover">
                        <div id="blogCover" class="mt-2"></div>
                    </x-admin.form.group>

                    <hr class="my-2">
                    <x-admin.form.switch name="is_published" :label="__('admin.blog.is_published')" :checked="(bool) $post->is_published" />
                </x-admin.card>

                <div class="admin-form-actions">
                    <a href="{{ route('admin.blog-posts.index') }}" class="admin-btn admin-btn-outline">{{ __('admin.common.cancel') }}</a>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i>{{ __('admin.common.save') }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
