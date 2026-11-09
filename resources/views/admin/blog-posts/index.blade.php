@extends('admin.layouts.master')

@section('title', __('admin.blog.title').' — '.__('admin.app_name'))
@section('page_title', __('admin.blog.title'))

@section('content')
    <x-admin.page-header :title="__('admin.blog.title')" :subtitle="__('admin.blog.subtitle')">
        <x-slot name="actions">
            <a href="{{ route('admin.blog-posts.create') }}" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i>{{ __('admin.blog.new') }}</a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card :padding="false">
        @if ($posts->isEmpty())
            <x-admin.empty :message="__('admin.blog.no_posts')" icon="bi-journal-text" :action="route('admin.blog-posts.create')" :actionLabel="__('admin.blog.new')" />
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.blog.article_title') }}</th>
                            <th>{{ __('admin.common.status') }}</th>
                            <th>{{ __('admin.blog.date') }}</th>
                            <th class="text-end">{{ __('admin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            <tr>
                                <td>
                                    <div class="admin-cell-main">{{ $post->title }}</div>
                                    <div class="admin-cell-sub">{{ $post->slug }}</div>
                                </td>
                                <td>
                                    <x-admin.badge :tone="$post->is_published ? 'success' : 'neutral'" :label="$post->is_published ? __('admin.blog.published') : __('admin.blog.draft')" />
                                </td>
                                <td>{{ $post->published_at ? $post->published_at->translatedFormat('j M Y') : '—' }}</td>
                                <td class="text-end">
                                    <div class="admin-table-actions">
                                        <a href="{{ route('admin.blog-posts.edit', $post) }}" class="admin-btn admin-btn-outline admin-btn-icon"><i class="bi bi-pencil"></i></a>
                                        <form method="post" action="{{ route('admin.blog-posts.destroy', $post) }}" data-confirm="{{ __('admin.common.confirm_delete') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="admin-btn admin-btn-danger admin-btn-icon"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.card>

    {{ $posts->links() }}
@endsection
