# Aroma brand fonts

The Aroma Brand Guidelines specify licensed, proprietary typefaces. They are
**not** committed to the repo. Drop the licensed web font files here and the
`@font-face` rules in `public/css/aroma.css` (and the SCSS source) pick them
up automatically. Until then the theme falls back to the Google Fonts stacks
below, chosen to match each font's role and character.

Per the Brand Guidelines (p.15-19): **Manier** is the English heading/display
typeface, **Luxury** is the Arabic-only heading/display typeface — they are
not interchangeable. Drop `manier.woff2` / `luxury.woff2` into this folder and
the matching locale's headings switch over with no further changes.

| Role | Brand font | Expected file | Fallback stack (until licensed file is added) |
|------|-----------|---------------|----------------|
| Headings (EN) | Manier | `manier.woff2` | Playfair Display → Georgia → serif |
| Body (EN) | Helvetica Neue | *(system)* | Inter → Helvetica → Arial → sans-serif |
| Script accent | Snell Roundhand | *(system)* | Tangerine → Brush Script MT → cursive |
| Headings (AR) | Luxury | `luxury.woff2` | El Messiri → Amiri → serif |
| Body (AR) | Helvetica Neue LT Arabic | `helvetica-neue-lt-arabic.woff2` | IBM Plex Sans Arabic → Tajawal → Tahoma → sans-serif |

The Google Fonts fallbacks are loaded via `<link>` in `layouts/app.blade.php`
and `layouts/auth.blade.php` — no local files needed for those.

Provide `.woff2` (and optionally `.woff`) for the best performance. Keep
`font-display: swap` so text renders immediately with the fallback.
