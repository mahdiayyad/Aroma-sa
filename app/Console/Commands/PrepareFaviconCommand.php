<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Crops the "A" glyph out of the current wordmark (aroma-logo-mark.png),
 * recolors it gold-on-burgundy (the same pairing already proven on the
 * navbar's aroma-wordmark-gold.png), and regenerates every favicon/app-icon
 * file in place. The previous set (public/favicon*.png/.ico,
 * apple-touch-icon.png, android-chrome-*.png) was generated 2026-08-11,
 * three weeks before the logo redesign landed, and still shows the old
 * icon-pattern-filled "A" — this replaces it with the current mark.
 *
 *   php artisan aroma:prepare-favicon
 */
class PrepareFaviconCommand extends Command
{
    protected $signature = 'aroma:prepare-favicon
                            {source? : Path under public/ of the transparent wordmark to crop from (default: images/brand/aroma-logo-mark.png)}
                            {--crop=0,0,560,650 : x,y,width,height of the "A" glyph region within the source, in source pixels}
                            {--background=330101 : Hex background colour for the square icon canvas}
                            {--ink=C9B39A : Hex colour to recolor the glyph ink to}
                            {--margin=0.14 : Fraction of the square canvas reserved as empty margin around the glyph}';

    protected $description = 'Regenerate favicon/apple-touch-icon/android-chrome icons from the current logo';

    public function handle(): int
    {
        $source = public_path($this->argument('source') ?? 'images/brand/aroma-logo-mark.png');

        if (! is_file($source)) {
            $this->error("Source not found: {$source}");

            return self::FAILURE;
        }

        [$x, $y, $w, $h] = array_map('intval', explode(',', $this->option('crop')));
        $background = $this->hex((string) $this->option('background'));
        $ink = $this->hex((string) $this->option('ink'));
        $margin = (float) $this->option('margin');

        $src = @imagecreatefrompng($source);
        if (! $src) {
            $this->error('Could not load source PNG (expected a transparent PNG).');

            return self::FAILURE;
        }

        $this->line('Source: '.str_replace(public_path(), 'public', $source)." ({$x},{$y},{$w}x{$h})");

        $glyph = $this->crop($src, $x, $y, $w, $h);
        $glyph = $this->recolorInk($glyph, $ink);

        $side = (int) round(max($w, $h) * (1 + $margin * 2));
        $master = $this->composeSquare($glyph, $side, $background);

        $dir = public_path();
        $sizes = [
            16 => 'favicon-16x16.png',
            32 => 'favicon-32x32.png',
            48 => 'favicon-48x48.png',
            192 => 'android-chrome-192x192.png',
            512 => 'android-chrome-512x512.png',
        ];

        foreach ($sizes as $size => $filename) {
            $resized = $this->resize($master, $size, $size);
            $this->savePng($resized, "{$dir}/{$filename}");
            $this->info("✓ {$filename}  {$size}x{$size}");
        }

        // apple-touch-icon: 180x180. Uses the same fully-opaque master as
        // every other size here (composeSquare() always fills a solid
        // background), which is what Apple wants — iOS ignores/blackens
        // alpha on home-screen icons.
        $apple = $this->resize($master, 180, 180);
        $this->savePng($apple, "{$dir}/apple-touch-icon.png");
        $this->info('✓ apple-touch-icon.png  180x180');

        // .ico packaging — GD can't write multi-resolution ICO, shell out to
        // ImageMagick (combines the PNGs already written above, doesn't
        // regenerate from scratch).
        $ico = "{$dir}/favicon.ico";
        $process = new Process(['convert', "{$dir}/favicon-16x16.png", "{$dir}/favicon-32x32.png", "{$dir}/favicon-48x48.png", $ico]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('ImageMagick failed to build favicon.ico: '.$process->getErrorOutput());

            return self::FAILURE;
        }
        $this->info('✓ favicon.ico');

        $this->line('Done — reload any page to see the new icons (browsers cache favicons aggressively; a hard refresh or new tab may be needed to see it live).');

        return self::SUCCESS;
    }

    /** @return array{int,int,int} */
    private function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @return \GdImage */
    private function crop($img, int $x, int $y, int $w, int $h)
    {
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $img, 0, 0, $x, $y, $w, $h);

        return $out;
    }

    /**
     * Repaint every non-fully-transparent pixel to one flat colour, keeping
     * each pixel's own alpha exactly as the source had it — same technique
     * as PrepareLogoCommand::recolorInk(), copied rather than shared since
     * it's a single ~15-line method and the two commands solve different
     * problems (this one composites onto a solid background afterward;
     * that one keeps everything transparent for the intro animation).
     *
     * @param  array{int,int,int}  $rgb
     * @return \GdImage
     */
    private function recolorInk($img, array $rgb)
    {
        [$r, $g, $b] = $rgb;
        $w = imagesx($img);
        $h = imagesy($img);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $alpha = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
                if ($alpha >= 127) {
                    continue; // fully transparent — leave as-is
                }
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
            }
        }

        return $out;
    }

    /**
     * @param  array{int,int,int}  $background
     * @return \GdImage
     */
    private function composeSquare($glyph, int $side, array $background)
    {
        [$r, $g, $b] = $background;

        $canvas = imagecreatetruecolor($side, $side);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));

        $gw = imagesx($glyph);
        $gh = imagesy($glyph);
        $destX = (int) round(($side - $gw) / 2);
        $destY = (int) round(($side - $gh) / 2);

        // Blend the glyph's per-pixel alpha onto the solid background
        // (imagecopy respects the destination's alphablending mode).
        imagealphablending($canvas, true);
        imagecopy($canvas, $glyph, $destX, $destY, 0, 0, $gw, $gh);

        return $canvas;
    }

    /** @return \GdImage */
    private function resize($img, int $w, int $h)
    {
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));

        return $out;
    }

    private function savePng($img, string $path): void
    {
        imagepng($img, $path, 6);
    }
}
