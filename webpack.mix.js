const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Aroma Asset Pipeline
 |--------------------------------------------------------------------------
 |
 | Compiles the brand SCSS (Bootstrap 5.3 + Aroma theme) and the app JS.
 | Because the store is bilingual we build two stylesheets:
 |   - app.css      : LTR (English)
 |   - app-rtl.css  : RTL (Arabic), post-processed with rtlcss
 |
 | Run: npm install && npm run prod
 */

mix.js('resources/js/app.js', 'public/js')
    .sass('resources/sass/aroma-theme.scss', 'public/css/app.css')
    .sass('resources/sass/aroma-theme.scss', 'public/css/app-rtl.css')
    .options({
        processCssUrls: false,
        postCss: [],
    })
    .version();

// Note: to emit true RTL, add `laravel-mix-rtl` (or rtlcss) and chain
// `.rtlcss()` on the second .sass() call. The Blade layout already switches
// between app.css / app-rtl.css based on the active locale direction.
