<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): BlogPost
    {
        return BlogPost::create($overrides + [
            'title' => ['ar' => 'عنوان المقال', 'en' => 'Article title'],
            'slug' => 'article-title',
            'body' => ['ar' => 'نص المقال', 'en' => 'Article text'],
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_the_blog_does_not_exist_until_something_is_published(): void
    {
        $this->article(['is_published' => false, 'published_at' => null]);

        $this->get(route('blog.index', 'en'))->assertNotFound();
        $this->get('/en')->assertDontSee(route('blog.index', 'en'), false);
        $this->get(route('sitemap'))->assertDontSee('/blog', false);
    }

    public function test_a_published_article_is_listed_linked_and_in_the_sitemap(): void
    {
        $this->article();

        $this->get(route('blog.index', 'en'))->assertOk()->assertSee('Article title');
        $this->get(route('blog.show', ['ar', 'article-title']))->assertOk()->assertSee('نص المقال');
        $this->get('/en')->assertSee('href="'.route('blog.index', 'en').'"', false);
        $this->get(route('sitemap'))->assertSee(route('blog.show', ['en', 'article-title']), false);
    }

    public function test_a_draft_article_cannot_be_opened(): void
    {
        $this->article(['is_published' => false, 'published_at' => null]);

        $this->get(route('blog.show', ['en', 'article-title']))->assertNotFound();
    }

    public function test_article_text_is_split_into_headings_and_paragraphs_and_escaped(): void
    {
        $this->article(['body' => ['en' => "Intro\r\n\r\n## A heading\nFirst line\n\n<script>alert(1)</script>"]]);

        $this->get(route('blog.show', ['en', 'article-title']))
            ->assertOk()
            ->assertSee('<h2>A heading</h2>', false)
            ->assertSee('<p>First line</p>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_an_admin_can_publish_an_article_and_republishing_keeps_its_date(): void
    {
        $this->actingAs($this->admin())->post(route('admin.blog-posts.store'), [
            'title' => ['ar' => 'عنوان', 'en' => 'New Post'],
            'body' => ['ar' => 'نص', 'en' => 'Text'],
            'is_published' => '1',
        ])->assertRedirect(route('admin.blog-posts.index'));

        $post = BlogPost::where('slug', 'new-post')->firstOrFail();
        $this->assertTrue($post->is_published);
        $this->assertNotNull($post->published_at);

        $post->update(['published_at' => now()->subMonth()]);
        $original = $post->fresh()->published_at;

        $this->actingAs($this->admin())->put(route('admin.blog-posts.update', $post), [
            'title' => ['ar' => 'عنوان', 'en' => 'New Post'],
            'body' => ['ar' => 'نص', 'en' => 'Edited'],
            'is_published' => '1',
        ])->assertRedirect(route('admin.blog-posts.index'));

        $this->assertTrue($original->equalTo($post->fresh()->published_at));
    }

    public function test_the_admin_screens_render_and_customers_are_kept_out(): void
    {
        $post = $this->article();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.blog-posts.index'))->assertOk()->assertSee('article-title');
        $this->actingAs($admin)->get(route('admin.blog-posts.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.blog-posts.edit', $post))->assertOk();

        $this->actingAs(User::factory()->create())->get(route('admin.blog-posts.index'))->assertForbidden();
    }
}
