# Aroma brand fonts

The Aroma Brand Guidelines specify licensed, proprietary typefaces. They are
**not** committed to the repo. Drop the licensed web font files here and the
`@font-face` rules in `public/css/aroma.css` (and the SCSS source) pick them up
automatically. Until then the theme falls back to the elegant stacks noted below.

> **Luxury is the primary heading typeface** for both Arabic and English (all
> headings, the logo, and display text). Drop `luxury.woff2` (or `.woff`,
> `.ttf`, `.otf` — all four formats are accepted) into this folder and every
> heading across the store switches to Luxury with no further changes. Until the
> file is present, headings fall back to Manier → Playfair Display → serif.

| Role | Brand font | Expected file | Fallback stack |
|------|-----------|---------------|----------------|
| Headings (EN) | Manier | `manier.woff2` | Playfair Display → Georgia → serif |
| Body (EN) | Helvetica Neue | *(system)* | Helvetica → Arial → sans-serif |
| Script accent | Snell Roundhand | *(system)* | Brush Script MT → cursive |
| Headings (AR) | Luxury | `luxury.woff2` | Amiri → Traditional Arabic → serif |
| Body (AR) | Helvetica Neue LT Arabic | `helvetica-neue-lt-arabic.woff2` | Segoe UI → Tahoma → sans-serif |

Provide `.woff2` (and optionally `.woff`) for the best performance. Keep
`font-display: swap` so text renders immediately with the fallback.
