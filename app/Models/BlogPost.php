<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasTranslations;

    protected $guarded = ['id'];

    /** @var array<int,string> */
    protected array $translatable = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    /**
     * The article split for display. Authors write plain text — a blank line
     * starts a new paragraph, a line starting with "## " is a heading — and
     * every block is escaped on output, so no HTML sanitiser is needed.
     *
     * @return array<int,array{type:string,text:string}>
     */
    public function blocks(): array
    {
        $text = preg_replace('/^## .*$/mu', "\n$0\n", str_replace("\r\n", "\n", (string) $this->body));

        return array_map(function (string $block): array {
            return strncmp($block, '## ', 3) === 0
                ? ['type' => 'heading', 'text' => trim(substr($block, 3))]
                : ['type' => 'paragraph', 'text' => trim($block)];
        }, preg_split('/\n\s*\n/u', trim($text)));
    }

    /** The author's summary, or the start of the article when there isn't one. */
    public function summary(): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        $first = collect($this->blocks())->firstWhere('type', 'paragraph');

        return Str::limit($first['text'] ?? '', 160);
    }
}
