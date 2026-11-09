<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Category;
use Database\Seeders\StoreContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_subcategories_and_unpublished_drafts_and_is_safe_to_rerun(): void
    {
        $abaya = Category::factory()->create(['slug' => 'abaya']);

        $this->seed(StoreContentSeeder::class);
        $this->seed(StoreContentSeeder::class);

        $this->assertSame(3, Category::where('parent_id', $abaya->id)->count());
        $this->assertSame(3, BlogPost::count());
        $this->assertSame(0, BlogPost::published()->count());
    }

    public function test_it_never_overwrites_an_article_that_was_edited_or_published(): void
    {
        Category::factory()->create(['slug' => 'abaya']);
        $this->seed(StoreContentSeeder::class);

        $post = BlogPost::where('slug', 'abaya-fabrics-guide')->firstOrFail();
        $post->update(['title' => ['ar' => 'معدّل', 'en' => 'Edited'], 'is_published' => true]);

        $this->seed(StoreContentSeeder::class);

        $this->assertSame('Edited', $post->fresh()->translate('title', 'en'));
        $this->assertTrue($post->fresh()->is_published);
    }
}
