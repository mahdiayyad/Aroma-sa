<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\HeroImageProcessor;
use Illuminate\Console\Command;

/**
 * Runs every raw hero-banner upload through HeroImageProcessor: smart-cropped
 * to 16:9, resized to exactly 1920x1080, denoised, sharpened, and given a
 * mild contrast/vibrance lift — so whatever gets dropped into
 * storage/app/hero-incoming/ (any resolution, any aspect ratio) comes out
 * the other end matching every other slide in the carousel.
 *
 * This is the "process each image before using it in the carousel" step:
 * drop the raw file(s) into storage/app/hero-incoming/, run this command,
 * then wire the resulting public/images/hero/<slug>.jpg into $heroSlides in
 * resources/views/home/index.blade.php the same way as any other slide —
 * this command only handles the image itself, not the slide's title/CTA/
 * link, which still needs a human's judgment call per image.
 *
 *   php artisan hero:process incoming-photo.jpg --slug=abaya-rack
 *   php artisan hero:process --all
 */
class ProcessHeroImages extends Command
{
    protected $signature = 'hero:process
                            {file? : Filename under storage/app/hero-incoming (or an absolute path) to process}
                            {--slug= : Output filename without extension (defaults to the source filename, slugified)}
                            {--all : Process every image currently in storage/app/hero-incoming}';

    protected $description = 'Crop, upscale, and enhance a raw hero-banner upload into a consistent 1920x1080 carousel-ready slide';

    private function incomingDir(): string
    {
        return storage_path('app/hero-incoming');
    }

    private function outputDir(): string
    {
        return public_path('images/hero');
    }

    public function handle(HeroImageProcessor $processor): int
    {
        if (! is_dir($this->incomingDir())) {
            mkdir($this->incomingDir(), 0755, true);
        }

        $jobs = $this->option('all') ? $this->allIncomingJobs() : $this->singleJob();

        if ($jobs === null) {
            return self::FAILURE;
        }

        if (empty($jobs)) {
            $this->warn('Nothing to process — storage/app/hero-incoming is empty.');
            $this->line('Drop raw images there, or pass a filename directly.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($jobs as $slug => $sourcePath) {
            $destination = $this->outputDir().'/'.$slug.'.jpg';
            $this->line("Processing {$slug}...");

            try {
                $processor->process($sourcePath, $destination);
                [$width, $height] = getimagesize($destination);
                $this->info("  ✓ {$slug}.jpg  {$width}x{$height}  ".$this->formatBytes(filesize($destination)));
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  ✗ {$slug}: {$e->getMessage()}");
            }
        }

        if ($failures > 0) {
            $this->warn("{$failures} image(s) failed — see above.");

            return self::FAILURE;
        }

        $this->line('Done. Wire the finished file(s) into $heroSlides in resources/views/home/index.blade.php to add them to the carousel.');

        return self::SUCCESS;
    }

    /** @return array<string,string>|null slug => absolute source path, or null on error (already reported) */
    private function singleJob(): ?array
    {
        $file = $this->argument('file');

        if (! $file) {
            $this->error('Pass a filename, or use --all to process everything in storage/app/hero-incoming.');

            return null;
        }

        $path = $this->resolvePath($file);

        if (! $path) {
            $this->error("File not found: {$file}");
            $this->line('Looked in storage/app/hero-incoming and as a direct path.');

            return null;
        }

        $slug = $this->option('slug') ?: $this->slugify(pathinfo($path, PATHINFO_FILENAME));

        return [$slug => $path];
    }

    /** @return array<string,string> slug => absolute source path */
    private function allIncomingJobs(): array
    {
        $jobs = [];

        foreach ((glob($this->incomingDir().'/*') ?: []) as $path) {
            if (! is_file($path) || ! $this->isImage($path)) {
                continue;
            }
            $jobs[$this->slugify(pathinfo($path, PATHINFO_FILENAME))] = $path;
        }

        return $jobs;
    }

    private function resolvePath(string $file): ?string
    {
        if (is_file($file)) {
            return $file;
        }

        $underIncoming = $this->incomingDir().'/'.ltrim($file, '/');
        if (is_file($underIncoming)) {
            return $underIncoming;
        }

        return null;
    }

    private function isImage(string $path): bool
    {
        return in_array(@exif_imagetype($path), [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true);
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return trim($slug, '-') ?: 'hero-slide';
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1).'MB'
            : round($bytes / 1024).'KB';
    }
}
