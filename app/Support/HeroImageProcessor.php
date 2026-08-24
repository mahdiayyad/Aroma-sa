<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Turns a raw hero-banner source image — any resolution, any aspect ratio —
 * into a finished carousel slide: cropped to 16:9 around its most detailed
 * region, resized to exactly 1920x1080, denoised, sharpened, and given a
 * mild contrast/vibrance lift. Every slide in $heroSlides ends up the same
 * dimensions and the same visual "grade" regardless of what the original
 * photo looked like — that consistency is the whole point of routing every
 * upload through this one class rather than dropping files straight into
 * public/images/hero/.
 *
 * No AI/ML is involved (none is available in this environment — no GPU, no
 * PyTorch/OpenCV, no image-processing API key configured). This is classical
 * image processing: Lanczos resampling, an unsharp mask, ImageMagick's
 * sigmoidal contrast curve, a mild saturation lift, and despeckling. It can
 * meaningfully sharpen and clean up a soft or slightly noisy photo, but it
 * cannot invent detail a low-resolution source never had — a 350px-tall
 * source upscaled through here will look better than a naive resize, but
 * still softer than a genuinely high-res source. There is currently no way
 * around that without real AI upscaling (see the note in the class-level
 * README this ships next to, hero-incoming/README.md).
 *
 * The crop window is chosen in pure PHP/GD — a small edge-energy scan over a
 * downsampled thumbnail (classical "entropy crop": the window with the most
 * visual detail wins, which in practice keeps faces/products/text/logos in
 * frame rather than plain background). ImageMagick has no equivalent
 * auto-detect flag, so that step runs first and only feeds a crop rectangle
 * into a single ImageMagick pass, which does the actual crop, the Lanczos
 * resize, and every enhancement — real Lanczos-quality output, not GD's
 * (rougher) resampling.
 *
 *   (new HeroImageProcessor())->process($rawPath, public_path('images/hero/abaya-rack.jpg'));
 */
class HeroImageProcessor
{
    public const TARGET_WIDTH = 1920;

    public const TARGET_HEIGHT = 1080;

    /** Width of the downsampled thumbnail used only for the crop-window scan — kept small so the scan is instant regardless of source size. */
    private const ANALYSIS_WIDTH = 240;

    /**
     * @param  string  $sourcePath  Any JPEG/PNG/WEBP, any size — a raw upload.
     * @param  string  $destinationPath  Where the finished JPEG is written (directory created if missing).
     */
    public function process(string $sourcePath, string $destinationPath): void
    {
        if (! is_file($sourcePath)) {
            throw new RuntimeException("Source image not found: {$sourcePath}");
        }

        [$width, $height] = $this->dimensions($sourcePath);
        [$cropX, $cropY, $cropW, $cropH] = $this->findCropWindow($sourcePath, $width, $height);

        $destinationDir = dirname($destinationPath);
        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $this->runImageMagickPipeline($sourcePath, $destinationPath, $cropX, $cropY, $cropW, $cropH);
    }

    /** @return array{0:int,1:int} */
    private function dimensions(string $path): array
    {
        $size = getimagesize($path);
        if (! $size) {
            throw new RuntimeException("Unreadable image: {$path}");
        }

        return [$size[0], $size[1]];
    }

    /**
     * The crop rectangle (in source-image pixels) that keeps the target 16:9
     * ratio while covering whichever region — full width or full height,
     * whichever the source doesn't already match — scores highest on the
     * edge-energy scan.
     *
     * @return array{0:int,1:int,2:int,3:int} [x, y, width, height]
     */
    private function findCropWindow(string $path, int $width, int $height): array
    {
        $targetRatio = self::TARGET_WIDTH / self::TARGET_HEIGHT;
        $sourceRatio = $width / $height;

        // Already (near enough) the right ratio — nothing meaningful to crop.
        if (abs($sourceRatio - $targetRatio) < 0.01) {
            return [0, 0, $width, $height];
        }

        $thumb = $this->loadDownsampled($path, $width, $height);
        $thumbWidth = imagesx($thumb);
        $thumbHeight = imagesy($thumb);
        $energy = $this->edgeEnergyMap($thumb);
        imagedestroy($thumb);

        if ($sourceRatio > $targetRatio) {
            // Source is wider than 16:9 — keep full height, slide a window
            // across the width and keep the one with the most detail.
            $windowWidth = (int) round($thumbHeight * $targetRatio);
            $columns = $this->sumAxis($energy, $thumbWidth, $thumbHeight, true);
            $columns = $this->applyCenterBias($columns, $windowWidth);
            $bestX = $this->bestOffset($columns, $windowWidth);
            $scale = $width / $thumbWidth;

            return [(int) round($bestX * $scale), 0, (int) round($windowWidth * $scale), $height];
        }

        // Source is taller than 16:9 — keep full width, slide vertically.
        $windowHeight = (int) round($thumbWidth / $targetRatio);
        $rows = $this->sumAxis($energy, $thumbWidth, $thumbHeight, false);
        $rows = $this->applyCenterBias($rows, $windowHeight);
        $bestY = $this->bestOffset($rows, $windowHeight);
        $scale = $height / $thumbHeight;

        return [0, (int) round($bestY * $scale), $width, (int) round($windowHeight * $scale)];
    }

    /** @return resource|\GdImage */
    private function loadDownsampled(string $path, int $width, int $height)
    {
        $src = $this->load($path);
        if (! $src) {
            throw new RuntimeException("Unsupported image type: {$path}");
        }

        $targetWidth = min(self::ANALYSIS_WIDTH, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($src);

        return $thumb;
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
            default:
                return null;
        }
    }

    /**
     * "How much visual detail lives here" per pixel: a Laplacian-style edge
     * kernel (an established pre-ML technique — high response on edges/
     * texture, near-zero on flat backgrounds). Faces, products, text and
     * logos score high; empty wall or sky scores low.
     *
     * @param  resource|\GdImage  $thumb
     * @return array<int,array<int,float>> [y][x] => energy
     */
    private function edgeEnergyMap($thumb): array
    {
        $width = imagesx($thumb);
        $height = imagesy($thumb);
        $gray = [];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($thumb, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray[$y][$x] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            }
        }

        $energy = [];
        for ($y = 0; $y < $height; $y++) {
            $energy[$y] = array_fill(0, $width, 0.0);
        }

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $center = $gray[$y][$x];
                $energy[$y][$x] = abs(
                    8 * $center
                    - $gray[$y - 1][$x - 1] - $gray[$y - 1][$x] - $gray[$y - 1][$x + 1]
                    - $gray[$y][$x - 1] - $gray[$y][$x + 1]
                    - $gray[$y + 1][$x - 1] - $gray[$y + 1][$x] - $gray[$y + 1][$x + 1]
                );
            }
        }

        return $energy;
    }

    /**
     * Collapses the 2D energy map down to one axis: a sum per column
     * (horizontal, for scanning left-to-right) or per row (vertical, for
     * scanning top-to-bottom).
     *
     * @param  array<int,array<int,float>>  $energy
     * @return array<int,float>
     */
    private function sumAxis(array $energy, int $width, int $height, bool $columns): array
    {
        $size = $columns ? $width : $height;
        $sums = array_fill(0, $size, 0.0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $index = $columns ? $x : $y;
                $sums[$index] += $energy[$y][$x];
            }
        }

        return $sums;
    }

    /** Sliding-window sum over the energy array; returns the start offset of the highest-scoring window. */
    private function bestOffset(array $energy, int $windowSize): int
    {
        $length = count($energy);
        $windowSize = max(1, min($windowSize, $length));

        $windowSum = 0.0;
        for ($i = 0; $i < $windowSize; $i++) {
            $windowSum += $energy[$i] ?? 0.0;
        }

        $bestSum = $windowSum;
        $bestStart = 0;

        for ($start = 1; $start <= $length - $windowSize; $start++) {
            $windowSum += ($energy[$start + $windowSize - 1] ?? 0.0) - ($energy[$start - 1] ?? 0.0);
            if ($windowSum > $bestSum) {
                $bestSum = $windowSum;
                $bestStart = $start;
            }
        }

        return $bestStart;
    }

    /**
     * Pulls the energy scores toward the middle before picking a window.
     * Raw edge-energy alone tends to hug whichever extreme edge is busiest —
     * on a typical marketing banner that's usually the product shot, which
     * can crop a headline sitting on a plain background clean out of frame
     * even though it's exactly the kind of content this needs to keep.
     *
     * The bias strength scales with how much is actually being cropped away
     * ($windowSize vs $length): a gentle 5-10% trim (most direct photography
     * here) barely needs it — there's little room for the window to move
     * anyway, and the energy-driven choice is usually already sound. A
     * drastic crop (the 2172x724 collage's already-tight quadrant banners
     * need ~40% of their width removed to reach 16:9) is exactly where a
     * skewed energy map is most likely to swing the window to a full edge
     * and lose something that matters — so that's where centering is
     * weighted most heavily, while a genuinely dominant off-center subject
     * can still win outright if its energy is overwhelming enough.
     *
     * @param  array<int,float>  $values
     */
    private function applyCenterBias(array $values, int $windowSize): array
    {
        $length = count($values);
        if ($length <= 1) {
            return $values;
        }

        $cropFraction = max(0.0, min(1.0, 1 - $windowSize / $length));
        $center = ($length - 1) / 2;
        $sigma = $length * max(0.18, 0.5 - $cropFraction * 0.5);
        $floor = max(0.05, 1 - $cropFraction * 2.1);

        $weighted = [];
        foreach ($values as $i => $value) {
            $distance = $i - $center;
            $weight = $floor + (1 - $floor) * exp(-($distance * $distance) / (2 * $sigma * $sigma));
            $weighted[$i] = $value * $weight;
        }

        return $weighted;
    }

    /**
     * One ImageMagick pass: crop to the computed window, Lanczos-resize to
     * exactly 1920x1080 (the crop is already 16:9, so this never stretches
     * anything — it only sets final pixel dimensions), then denoise →
     * sharpen → contrast → saturation, in that order (denoise before sharpen
     * so the sharpen doesn't amplify noise; contrast/saturation last so they
     * grade the cleaned-up image, not the raw one).
     */
    private function runImageMagickPipeline(string $source, string $destination, int $x, int $y, int $w, int $h): void
    {
        $process = new Process([
            'magick',
            $source,
            '-crop', "{$w}x{$h}+{$x}+{$y}",
            '+repage',
            '-filter', 'Lanczos',
            '-resize', self::TARGET_WIDTH.'x'.self::TARGET_HEIGHT.'!',
            '-despeckle',
            '-unsharp', '0x1.2+0.8+0.02',
            '-sigmoidal-contrast', '2x50%',
            '-modulate', '100,112,100',
            '-colorspace', 'sRGB',
            '-quality', '93',
            '-sampling-factor', '4:2:0',
            '-strip',
            $destination,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
