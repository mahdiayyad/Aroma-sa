# Hero banner uploads — staging folder

Drop raw hero-banner photography here (any resolution, any aspect ratio,
PNG/JPEG/WEBP), then run:

```
php artisan hero:process --all
```

Every file in this folder gets cropped to 16:9 around its most detailed
region, resized to exactly 1920x1080, denoised, sharpened, and given a mild
contrast/vibrance lift — see `App\Support\HeroImageProcessor` for exactly
what that pipeline does and why. The finished JPEG lands in
`public/images/hero/<slug>.jpg`, where `<slug>` is the source filename,
slugified (pass `--slug=name` when processing a single file to choose the
name yourself instead).

Processing one file at a time works too:

```
php artisan hero:process my-photo.jpg --slug=abaya-rack
```

The command only produces the image file — adding it to the carousel still
means adding an entry to `$heroSlides` in
`resources/views/home/index.blade.php` with its title/CTA/link, since that's
a copywriting call this pipeline has no way to make for you.

**What this pipeline can't do:** there's no AI/ML available in this
environment (no GPU, no PyTorch/OpenCV, no image-processing API key
configured) — this is classical image processing (Lanczos resampling, an
unsharp mask, ImageMagick's sigmoidal contrast, a mild saturation lift,
despeckling), not true AI super-resolution. It can meaningfully sharpen and
clean up a soft or slightly noisy photo, but it can't invent detail a
low-resolution source never had. Upload the highest-resolution source you
have — this pipeline gets you the rest of the way to "consistent Full HD
carousel slide," but it isn't a substitute for starting from a sharp photo.

Files placed here aren't deleted or modified by the command — safe to keep
your originals here as a source-of-truth archive if you'd like, though this
folder is git-ignored (see `.gitignore` next to this file) since raw
uploads don't belong in version control.
