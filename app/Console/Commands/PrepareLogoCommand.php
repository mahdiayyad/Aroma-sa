<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Turns the flat brand logo (dark artwork on a solid light ground) into
 * web-ready transparent assets for the loading animation:
 *
 *   aroma-logo-mark.png   full logotype + slogan, transparent
 *   aroma-wordmark.png    just "Aroma"        (animated letter-by-letter)
 *   aroma-slogan.png      just the tagline    (animated after the wordmark)
 *
 * The background colour is sampled from the corners, then every pixel gets an
 * alpha proportional to its distance from that colour — which keeps the
 * anti-aliased letter edges smooth instead of producing a jagged cut-out.
 *
 *   php artisan aroma:prepare-logo
 */
class PrepareLogoCommand extends Command
{
    protected $signature = 'aroma:prepare-logo
                            {source? : Path under public/ of the flat logo (auto-detected if omitted)}
                            {--tolerance= : Colour distance treated as pure background (auto: 26 PNG / 36 JPEG)}
                            {--feather=48 : Distance over which edges fade in}';

    protected $description = 'Remove the logo background and split it into transparent wordmark + slogan assets';

    /** Opacity below this is treated as compression noise and discarded. */
    private const NOISE_FLOOR = 0.12;

    /** GD alpha at or below this counts as real ink (0 = opaque, 127 = clear). */
    private const INK_ALPHA = 100;

    public function handle(): int
    {
        $source = $this->resolveSource($this->argument('source'));

        if (! $source) {
            $this->error('Logo not found.');
            $this->line('Save the flat logo as one of these (any extension works):');
            $this->line('  public/images/brand/aroma-logo-full.png | .jpg | .jpeg | .webp');

            return self::FAILURE;
        }

        $this->line('Source: '.str_replace(public_path(), 'public', $source));

        $image = $this->load($source);
        if (! $image) {
            $this->error('Unsupported image type (use PNG, JPG or WEBP).');

            return self::FAILURE;
        }

        // JPEG compression leaves ringing around high-contrast edges, so it needs
        // a slightly wider key than a clean PNG to avoid faint halos.
        $isJpeg = @exif_imagetype($source) === IMAGETYPE_JPEG;
        $tolerance = $this->option('tolerance') !== null
            ? (int) $this->option('tolerance')
            : ($isJpeg ? 36 : 26);

        $transparent = $this->removeBackground($image, $tolerance);
        $trimmed     = $this->trim($transparent);

        $dir = dirname($source);
        $this->savePng($trimmed, $dir.'/aroma-logo-mark.png');
        $this->info('✓ aroma-logo-mark.png  '.imagesx($trimmed).'x'.imagesy($trimmed));

        // Split the logotype from the slogan on the widest empty horizontal band.
        $split = $this->findHorizontalGap($trimmed);

        if ($split === null) {
            $this->warn('Could not find a gap between the logotype and slogan — only the full mark was written.');
            $this->line('The animation will fall back to revealing the whole mark.');

            return self::SUCCESS;
        }

        $word   = $this->crop($trimmed, 0, 0, imagesx($trimmed), $split);
        $slogan = $this->crop($trimmed, 0, $split, imagesx($trimmed), imagesy($trimmed) - $split);

        $word   = $this->trim($word);
        $slogan = $this->trim($slogan);

        $this->savePng($word, $dir.'/aroma-wordmark.png');
        $this->savePng($slogan, $dir.'/aroma-slogan.png');

        $this->info('✓ aroma-wordmark.png   '.imagesx($word).'x'.imagesy($word));
        $this->info('✓ aroma-slogan.png     '.imagesx($slogan).'x'.imagesy($slogan));
        $this->line('Done — reload the storefront to see the loading animation.');

        return self::SUCCESS;
    }

    /** Find the logo whatever extension it was saved with. */
    private function resolveSource(?string $given): ?string
    {
        if ($given) {
            $path = public_path($given);
            if (is_file($path)) {
                return $path;
            }
            // Same name, different extension (e.g. asked for .png but saved .jpg).
            $base = preg_replace('/\.[^.]+$/', '', $path);
            foreach ((glob($base.'.*') ?: []) as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }

            return null;
        }

        foreach ((glob(public_path('images/brand/aroma-logo-full.*')) ?: []) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return resource|\GdImage|null */
    private function load(string $path)
    {
        switch (@exif_imagetype($path)) {
            case IMAGETYPE_PNG:
                return @imagecreatefrompng($path);
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        }

        return null;
    }

    /**
     * Colour-key the solid background out, feathering the edges so the
     * anti-aliased glyph outlines stay smooth.
     *
     * @param  resource|\GdImage  $src
     * @return resource|\GdImage
     */
    private function removeBackground($src, int $tolerance)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $feather = max(1, (int) $this->option('feather'));

        [$br, $bg, $bb] = $this->backgroundColour($src, $w, $h);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($src, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $distance = sqrt(($r - $br) ** 2 + ($g - $bg) ** 2 + ($b - $bb) ** 2);

                if ($distance <= $tolerance) {
                    continue; // pure background → stays transparent
                }

                $opacity = min(1.0, ($distance - $tolerance) / $feather);

                // Noise floor: JPEG ringing leaves barely-visible speckle across
                // the background. Dropping it keeps the artwork clean and lets
                // the trim/split below find the true bounds.
                if ($opacity < self::NOISE_FLOOR) {
                    continue;
                }

                $gdAlpha = 127 - (int) round($opacity * 127);

                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $gdAlpha));
            }
        }

        return $out;
    }

    /** Median-ish background colour sampled from the four corners. */
    private function backgroundColour($src, int $w, int $h): array
    {
        $samples = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        $r = $g = $b = 0;

        foreach ($samples as [$x, $y]) {
            $rgb = imagecolorat($src, $x, $y);
            $r += ($rgb >> 16) & 0xFF;
            $g += ($rgb >> 8) & 0xFF;
            $b += $rgb & 0xFF;
        }

        $n = count($samples);

        return [(int) round($r / $n), (int) round($g / $n), (int) round($b / $n)];
    }

    /** Row index of the middle of the widest fully-transparent horizontal band. */
    private function findHorizontalGap($img): ?int
    {
        $w = imagesx($img);
        $h = imagesy($img);

        $empty = [];
        for ($y = 0; $y < $h; $y++) {
            $blank = true;
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) <= self::INK_ALPHA) { // has ink
                    $blank = false;
                    break;
                }
            }
            $empty[$y] = $blank;
        }

        // Only consider bands in the middle of the artwork.
        $bestStart = $bestLen = null;
        $start = null;

        for ($y = (int) ($h * 0.25); $y < (int) ($h * 0.9); $y++) {
            if ($empty[$y]) {
                $start = $start ?? $y;
                continue;
            }
            if ($start !== null) {
                $len = $y - $start;
                if ($bestLen === null || $len > $bestLen) {
                    $bestLen   = $len;
                    $bestStart = $start;
                }
                $start = null;
            }
        }

        if ($bestLen === null || $bestLen < 3) {
            return null;
        }

        return (int) ($bestStart + $bestLen / 2);
    }

    /** Crop away fully-transparent margins. */
    private function trim($img)
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) <= self::INK_ALPHA) {
                    $minX = min($minX, $x); $maxX = max($maxX, $x);
                    $minY = min($minY, $y); $maxY = max($maxY, $y);
                }
            }
        }

        if ($maxX < 0) {
            return $img; // nothing found — leave as-is
        }

        return $this->crop($img, $minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1);
    }

    private function crop($img, int $x, int $y, int $w, int $h)
    {
        $out = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $img, 0, 0, $x, $y, $w, $h);

        return $out;
    }

    private function savePng($img, string $path): void
    {
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagepng($img, $path, 6);
    }
}
