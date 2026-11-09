<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    use HandlesAdminForms;

    public function index(): View
    {
        return view('admin.blog-posts.index', [
            'posts' => BlogPost::orderBy('id', 'desc')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog-posts.form', ['post' => new BlogPost()]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        BlogPost::create($this->payload($request));

        return redirect()->route('admin.blog-posts.index')->with('status', __('admin.blog.saved'));
    }

    public function edit(BlogPost $blogPost): View
    {
        return view('admin.blog-posts.form', ['post' => $blogPost]);
    }

    public function update(BlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $blogPost->update($this->payload($request, $blogPost));

        return redirect()->route('admin.blog-posts.index')->with('status', __('admin.blog.saved'));
    }

    public function destroy(BlogPost $blogPost): RedirectResponse
    {
        $blogPost->delete();

        return redirect()->route('admin.blog-posts.index')->with('status', __('admin.blog.deleted'));
    }

    /** @return array<string,mixed> */
    private function payload(BlogPostRequest $request, ?BlogPost $post = null): array
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug('blog_posts', $data['slug'] ?? null, $data['title']['en'], optional($post)->id);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $this->storeUpload($request->file('cover_image'), 'blog');
        } else {
            unset($data['cover_image']);
        }

        // Only the first publish sets the date, so taking an article down to
        // fix something doesn't make it look new when it goes back up.
        if ($data['is_published'] && ! optional($post)->published_at) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
