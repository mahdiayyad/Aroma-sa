<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Contracts\View\View;

class BlogController extends Controller
{
    /** 404 until something is published, so an empty blog is never indexed. */
    public function index(): View
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->paginate(9);

        abort_if($posts->isEmpty(), 404);

        return view('blog.index', ['posts' => $posts]);
    }

    public function show(string $locale, string $slug): View
    {
        return view('blog.show', [
            'post' => BlogPost::published()->where('slug', $slug)->firstOrFail(),
        ]);
    }
}
