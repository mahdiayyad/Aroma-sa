# Aroma (Aroma Gift Center) — SEO Growth Strategy: Saudi Arabia & GCC

**Prepared:** 2026-09-21 · **Scope:** premium Saudi e-commerce (abayas, perfumes, gifts, fashion) · **Markets:** KSA first, GCC second · **Languages:** Arabic (primary), English

---

## 0. Executive summary

**Verdict.** Aroma has a better technical base than most new Saudi stores (locale-prefixed URLs, hreflang on catalog pages, Product + Breadcrumb + ItemList schema, a dynamic sitemap, a real robots.txt). But it is not yet *rankable*. Three things stand between the current build and organic revenue:

1. **The catalog has no SEO substance.** All 18 products share an identical 74-character description (67 in Arabic); every category description is 33–48 characters; every product has one image and it is the placeholder; nobody has ever seen a `meta_title` because the templates never render it. Google has nothing to rank.
2. **Half the site is invisible in English and un-hreflanged.** About, Guides (the only editorial content), Contact, Terms and Privacy live on single, locale-agnostic URLs that flip language by session cookie. Googlebot has no session, so it only ever sees Arabic — and none of these pages carry hreflang.
3. **The head-term battle is unwinnable in year one; the niche battle is winnable.** "عبايات" and "عطور" are owned by Noon, Namshi, Amazon.sa, Arabian Oud, Goldenscent and others. Aroma should aim to own **luxury/designer/occasion abayas, gift-ready perfumes, and the "send a personal gift" workflow** (anonymous sender, handwritten-style card, signature — features almost no competitor has) — and use that authority to climb toward the head terms in months 12–24.

**What "market leadership" realistically means.** Share-of-voice leadership in the niche *luxury abayas + gifting* within 12–18 months; top-3 for 40–60 long-tail commercial terms in 6–9 months; head terms ("عبايات", "عطور", "هدايا") only after 18–30 months of authority building.

**The 10 highest-impact moves (details in §1.3 and §11):**

| # | Move | Why it matters |
|---|---|---|
| 1 | Real product content sprint (unique AR/EN copy, photography, size/scent data) | Nothing else works without it |
| 2 | Move static pages under `/{locale}/…` with hreflang + 301s | Unlocks English + the guides for indexing |
| 3 | Homepage H1 + keyword-led title/meta; resolve AR/EN positioning conflict | The homepage currently has **no H1** and the meta description differs by language |
| 4 | Render `meta_title`/`meta_description`; enforce unique copy | Admin SEO fields are currently dead code |
| 5 | Fix `og:image` (SVG placeholder is the OG image on every product) | WhatsApp is the dominant sharing channel in KSA |
| 6 | Internal linking rebuild (nav, footer, related products, brand hubs) | Categories currently get links only from 6 homepage tiles |
| 7 | Category SEO copy + curated collection landing pages | Captures the keyword clusters in §2 |
| 8 | Performance sprint (defer sitewide libs, WebP/srcset, dimensions, fonts) | Mobile Android/4G is the Saudi median device |
| 9 | Seasonal engine (Ramadan, Eid, White Friday, National Day evergreen URLs) | Saudi gifting revenue is heavily seasonal |
| 10 | Digital PR + Mawthooq-licensed creators + Maroof trust layer | Authority + local trust in a low-trust-by-default category |

**Data limits (honest disclosure).** I audited the codebase and crawled 13 representative URLs on the running local build. I did **not** have access to Search Console, GA4, CrUX, Ahrefs/Semrush, Keyword Planner or a live production domain. Therefore: keyword difficulty/value labels are analyst estimates (no invented search volumes); Core Web Vitals figures are code-based projections, not field data; competitor and backlink gaps are hypotheses to verify in week 1 (§3). The revenue model in §11 is a set of explicit-assumption planning scenarios, not a forecast.

---

## 1. Technical SEO audit

### 1.1 Method

Live crawl (Googlebot UA) of: `/ar`, `/en`, category (AR/EN, incl. `?sort=…&page=2`), product (AR/EN), `/about`, `/guides`, `/guides/sizing`, `/contact`, `/terms`, `/privacy-policy`, `robots.txt`, `sitemap.xml`, plus response headers; source review of routes, layout, controllers, models, Blade templates, sitemap, `Seo` helper, config, CSS/JS assets; DB inspection of catalog content.

### 1.2 Scorecard

| Area | Status | Evidence |
|---|---|---|
| Site architecture | **Good base, thin depth** | Flat: home → 6 categories → 18 products. Category hierarchy supported in the model (`parent_id`) but unused. No brand, collection, guide-hub or journal layer. |
| Crawlability | **Fair** | `robots.txt` is generated and blocks cart/checkout/account/admin correctly. Does **not** block facet params (`?sort`, `?brand`, `?price_min/max`) or `/locale/{x}` redirect endpoints. Always `Allow: /` regardless of `APP_ENV` (staging would be crawlable). |
| Indexability | **Fair** | Everything `index, follow` incl. paginated/faceted URLs; no `max-image-preview`. Transactional pages correctly disallowed in robots but carry no `noindex` meta (fine if robots is respected; add meta for safety on `/cart`, `/checkout/*`). |
| XML sitemap | **Weak** | Single flat file, 54 URLs. No hreflang `xhtml:link`; `lastmod` for home and static pages = request time (teaches Google to ignore lastmod); `/guides/*` (5 pages) missing; no image entries; `priority` is ignored by Google. |
| Duplicate content | **Risk (data-driven)** | 18/18 products have the same description text; category meta descriptions fall back to 39-char placeholders → duplicate/near-duplicate meta across 24 pages. Facets canonical to base (good). |
| Thin content | **Critical** | Category copy 33–48 chars. Guides ≈200–470 words incl. chrome. Homepage ≈465 words, almost all UI. No blog/journal. |
| Canonicals | **Mixed** | Self-canonical on unique pages (good). `?page=2` canonicalises to page 1 (wrong — paginated pages should self-canonicalise). Canonical built from `url()->current()`; `APP_URL=http://localhost:8000` and `TrustProxies::$proxies` is unset → behind a proxy/CDN the canonical/hreflang can emit `http://` or the wrong host. **Verify on production.** |
| Pagination | **Weak** | 12/page, `withQueryString()`, `{{ $products->links() }}`; every page has identical `<title>` and meta. Fine now (≤12 products/category); breaks at scale. |
| Structured data | **Partial** | Organization + WebSite (all pages), Product + BreadcrumbList (PDP), BreadcrumbList + ItemList (PLP). Gaps in §1.6. |
| Image SEO | **Weak** | No WebP/AVIF anywhere (0 files); no `srcset`; **0 of 23** homepage `<img>` have `width`/`height`; product images have no processing pipeline (only hero/logo commands exist). Alt text: PDP falls back to product name (OK); one alt-less `<img>` per page. Filenames are admin-upload names. |
| URL structure | **Good** | `/ar|en/category/{slug}`, `/ar|en/product/{slug}`; lowercase; hyphenated; no params on canonical URLs. Trailing-slash 301 exists in `.htaccess`. `/EN` (uppercase) 404s (minor). |
| Multilingual | **Split** | Catalog pages: correct `ar`/`en`/`x-default` triplet, reciprocal, absolute URLs. **Static pages: no locale URL, no hreflang, language chosen by session** (Googlebot gets `ar`). `/` → **302** → `/{session locale}`. |
| Mobile-friendliness | **Good** | Responsive Bootstrap 5.3, RTL build, viewport meta set, touch-first quick-add and sticky mobile cart bar. |
| Core Web Vitals | **At risk** | §1.4. |
| Site search | **Absent** | Header search form submits `?q=` to the home route; nothing reads `q`. Dead feature; blocks `SearchAction` schema and loses intent data. |
| Platform | **Risk** | PHP **7.4.7** (EOL Nov 2022), Laravel **8** (EOL). `X-Powered-By` exposed. Security/uptime incidents are an SEO risk (hacked-site flags, downtime). |
| Trust/E-E-A-T | **Thin** | Saudi E-Authenticate seal present (good). No CR/VAT numbers, address, phone or WhatsApp rendered (`config('aroma.contact.*')` empty), no social profiles wired, 3 approved reviews. |

### 1.3 Critical issues ranked by impact

**P0 — fix before any content/link investment**

1. **Locale-agnostic static pages.** `/about`, `/guides`, `/guides/{sizing,fit,care,returns}`, `/contact`, `/terms`, `/privacy-policy` are single URLs whose language follows `session('locale')`. Confirmed: no-cookie request returns `lang="ar"`, no hreflang. Consequences: English never indexable; the guides — Aroma's only editorial content and best long-tail asset — cannot rank in English; hreflang cluster incomplete. **Fix:** move under `/{locale}/…` (routes/web.php lines 168–180), 301 the legacy URLs to the Arabic version, emit hreflang via `Seo::alternateUrls()` (already works for any route with a `{locale}` param), add to sitemap.
2. **Homepage has no `<h1>`.** Hero text is baked into the JPEG. Title is `Aroma — أيقظ حواسك` (18 chars): no product keyword, no market, no brand disambiguation. **Fix:** visible H1 + keyword-led title/meta (templates in §5).
3. **AR/EN positioning conflict on the homepage.** `storefront.hero.subtitle`: AR = abayas ("عبايات أنيقة بتفاصيل فاخرة…"), EN = fragrances/flowers/beauty (the abaya sentence is commented out). That string is also the **meta description and `og:description`**, so Google sees two different businesses. **Fix:** one positioning, translated.
4. **Dead SEO fields.** Admin forms for products, categories and brands expose `meta_title`/`meta_description`, but no storefront view reads `meta_title`, and descriptions fall back to 33–74-char placeholders. Terms page meta description falls back to the English tagline on an Arabic page. **Fix:** `@section('title', $x->translate('meta_title') ?: template)`; enforce minimum lengths in admin validation; per-type fallbacks.
5. **`og:image` broken.** Every PDP's `og:image`/`twitter:image` is `/images/placeholder.svg` (SVG is not supported by WhatsApp, Facebook, X, LinkedIn); every other page uses the 512×512 favicon. No 1200×630. In KSA, WhatsApp/Snapchat/X sharing of product links is the primary referral path. **Fix:** branded default 1200×630 PNG/JPG; per-product raster OG image; never fall back to SVG.
6. **Pagination canonical.** `?page=2` → canonical page 1, identical title/meta. **Fix:** self-canonical on `?page=N` (strip `sort/brand/price_*`), title suffix "— الصفحة 2", keep facets canonical to the clean category.
7. **Facet & redirect-endpoint crawl waste.** Add `Disallow: /*?*sort=`, `/*?*price_min=`, `/*?*price_max=`, `/*?*brand=`, `/locale/`; keep `?page=` crawlable.
8. **Catalog content.** See §7 for the standard; this is the single largest lever.

**P1 — do within 30–60 days**

9. **Sitemap v2:** sitemap index (pages / categories / products / guides / journal / images), `xhtml:link` hreflang alternates, honest `lastmod` (only real content changes), drop `priority`.
10. **Structured data v2** (§1.6): complete Product/Offer, Organization `sameAs`/`contactPoint`/legal identifiers, real logo, `SearchAction` once search exists.
11. **Internal linking rebuild.** Primary nav "Categories" and "Shop" both link to `home#categories`; the footer contains **zero category links**; the mobile offcanvas lists only "Abayas". Category pages are linked from 6 homepage tiles and breadcrumbs only. **Fix:** header mega-menu/direct links to categories, footer category + guide columns, related-products module, brand hubs (§5).
12. **Site search** (Meilisearch/Scout with Arabic normalisation) or remove the dead form.
13. **Brand disambiguation.** "Aroma" is a common noun and the name of unrelated businesses. Use **"Aroma Gift Center" / "أروما"** consistently in `Organization.name` + `alternateName`, titles and About; otherwise brand-query capture is contested.
14. **Trust layer:** CR (السجل التجاري) number, VAT number, address/National Address short code (the checkout already supports it), phone, WhatsApp, social links in footer and Organization schema; register on **Maroof (معروف)**.
15. **Non-prod safety:** robots `Disallow: /` + `X-Robots-Tag: noindex` whenever `APP_ENV !== production`; HTTP auth on staging.

**P2 — 60–120 days**

16. Root `/` → **301** (not 302) to `/ar`; add explicit `https`, non-`www`/`www` canonical redirect at the edge; set `URL::forceScheme('https')` and configure `TrustProxies`.
17. `max-image-preview:large, max-snippet:-1` in robots meta (Discover/Images eligibility).
18. Stale `theme-color: #704F2F` (old palette) → `#330101`.
19. Case-insensitive locale (`/EN` → 301 `/en`).
20. Upgrade PHP 8.2/8.3 + Laravel 10/11.

### 1.4 Core Web Vitals & speed (code-based projection — verify with PageSpeed Insights/CrUX at launch)

**What loads on every public page (home measured):** 20–21 stylesheet links (6 external: Bootstrap, Bootstrap Icons, Google Fonts, select2, SweetAlert2, intl-tel-input; 14–15 local component files) and 13–14 script tags including **jQuery, select2, SweetAlert2, intl-tel-input + utils (≈1 MB uncompressed)** — libraries used only in checkout/auth/forms. Local CSS ≈260 KB raw, JS ≈144 KB raw. Google Fonts requests 6 families / 26 styles (Playfair ×7, Inter ×6, El Messiri ×4, IBM Plex Sans Arabic ×5, Tangerine ×2, Aref Ruqaa ×2) while the local brand fonts (Manier, AligarhArabic) are the intended primary faces. The 2.9 MB `public/fonts` and 12 MB `public/images` (incl. a 2.2 MB unused `perfume-hero.png`) inflate deploys and crawl surface.

| Metric | Projection (mid-range Android, 4G, Riyadh) | Target (p75, mobile) | Main drivers |
|---|---|---|---|
| LCP | 3.2–4.5 s (risk) | ≤ 2.0 s | 341 KB hero JPEG (no WebP/srcset); render-blocking CSS chain (20 files, 3 origins); font CSS blocking; no edge caching |
| INP | 150–300 ms (borderline) | ≤ 150 ms | jQuery + select2 + SweetAlert2 + custom carousel drag handlers on every page |
| CLS | 0.05–0.15 | ≤ 0.05 | 0/23 images with dimensions; font swap; quick-add panel; late-loading header badges |
| TTFB | ~90 ms local; unknown prod | ≤ 400 ms | Session + XSRF `Set-Cookie` on **every anonymous GET** → `Cache-Control: no-cache, private` → nothing cacheable at a CDN |

**First-visit intro splash.** `layouts/partials/intro.blade.php` locks scroll and covers the page for ~2.15 s (0.45 s with reduced motion) on every *first-ever* visit — precisely the profile of an organic-search landing. It does not necessarily worsen the LCP metric (the hero paints behind it) but it delays real visibility by ~2 s, lifts bounce, and can appear in Google's rendered screenshot. Cut to ≤ 600 ms or skip for non-home landings.

**Speed plan (ordered by ROI):**
1. Load select2 / SweetAlert2 / intl-tel-input / jQuery **only on checkout, auth, account and forms** via `@stack('head'/'scripts')`; keep the catalog on Bootstrap + ~4 KB of vanilla JS.
2. Self-host Bootstrap + icons subset (one origin, HTTP/2, long cache); trim Google Fonts to the faces actually rendered, `font-display: swap`, `preload` the two primary WOFF2; convert brand OTF → WOFF2 subsets.
3. Concatenate/minify the 14 local CSS files into 2 (critical + deferred).
4. Image pipeline: on upload generate WebP/AVIF at 480/768/1080/1600 widths, `srcset`/`sizes`, explicit `width`/`height`, `loading="lazy"` below the fold, `fetchpriority="high"` + `preload imagesrcset` for the hero.
5. Make anonymous catalog GETs session-free (locale is already in the URL) → `Cache-Control: public, s-maxage=300, stale-while-revalidate=86400` + CDN (Cloudflare or equivalent) with cookie-bypass for cart/checkout/account.
6. Brotli + long-cache rules (`.htaccess`/Nginx): `immutable` for versioned assets.
7. PHP 8.2/8.3 + OPcache + Laravel 10/11 upgrade.

### 1.5 Multilingual architecture (decision)

- Keep **URL-prefixed locales** (`/ar`, `/en`) — correct choice. Extend it to *every* indexable page.
- hreflang: `ar`, `en`, `x-default → ar` (current) is right for KSA launch. For GCC expansion add region-specific subfolders **only where the offer differs** (currency/shipping/phrasing): `/ae/ar`, `/ae/en`, `/kw/ar`… with `ar-AE`, `en-AE`, `ar-KW`; the KSA pair becomes `ar-SA`/`en-SA`. Do not clone content across countries unchanged.
- Slugs: keep **English keyword slugs on both locales** (`/ar/category/luxury-abayas`). Arabic slugs percent-encode into unreadable strings when pasted into WhatsApp — a real CTR cost — and Google does not weight URL language strongly. Put the Arabic keyword in the title/H1.
- Arabic content is **transcreated**, not machine-translated (§6).

### 1.6 Structured data gap table

| Type | Now | Add |
|---|---|---|
| Organization | name, url, logo (= 512 favicon with `?v=`) | `legalName`, `alternateName: ["أروما","Aroma"]`, `sameAs` (Instagram, TikTok, Snapchat, X, Maroof, LinkedIn), `contactPoint` (WhatsApp/phone/email, `availableLanguage: ["ar","en"]`, `areaServed: "SA"`), `address` (National Address), real square + wide logo, `foundingDate`, `vatID`/CR as `identifier` |
| WebSite | name, url, publisher, inLanguage | `SearchAction` once `/search` exists; `@id` on the canonical locale home, not `/` (which redirects) |
| Product | name, 1 image (placeholder), short description, sku, brand, Offer (price = `base_price`, availability), aggregateRating (only if reviews) | `image[]` (all gallery), full `description`, `mpn`/`gtin` when available, `color`/`material`/`size` for abayas, variant handling (`ProductGroup` + `hasVariant`, or `AggregateOffer` with `lowPrice`/`highPrice`), `Offer.priceValidUntil`, `hasMerchantReturnPolicy`, `shippingDetails`, `seller`, `review[]` (visible reviews only) |
| BreadcrumbList | present PDP/PLP | Add to guides/journal; final item should carry `item` URL for PDP |
| ItemList (PLP) | url + name | Add `image`; paginate consistently |
| Article | absent | Journal + guides: `Article`, `author` (Person, credentials), `datePublished`/`dateModified`, `publisher` |
| FAQPage | absent | Add where a visible FAQ exists. **Caveat:** since Aug 2023 Google shows FAQ rich results only for government/health sites — value here is machine readability for AI answers and eligibility for other engines, not a SERP feature. |
| HowTo | absent | Skip (rich result deprecated). |
| LocalBusiness/Store | absent | Only if a physical showroom/pickup point exists (§9). |

---

## 2. Keyword research

**How to read this section.** Difficulty (KD) = analyst estimate of how hard it is to rank *for a new domain in KSA*: L / M / H / VH. Commercial value = revenue per converted visit relative to Aroma's catalog: $ – $$$$. Priority: **P0** (must-win now), **P1** (win in 3–9 mo), **P2** (6–15 mo), **P3** (long-term head term). I have deliberately not invented search volumes; validate every row in week 1 with Keyword Planner + Ahrefs/Semrush (location: Saudi Arabia, language: Arabic and English) and re-rank by real volume. Arabic variants to cover in every cluster: عباية/عباءة, عطر/عطور, هدية/هدايا, ورد/زهور/باقة.

**Intent legend:** C = commercial/transactional, CI = commercial-investigation ("best", "how to choose"), N = navigational/brand, I = informational.

Landing-page notation: **[exists]** = current URL pattern; **[new]** = page type to build (§4–5).

### 2.1 Abayas

| Keyword (AR / EN) | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عبايات / abayas | C | VH | $$$$ | P3 | `/ar/category/abayas` [exists] |
| عبايات نسائية / women's abayas | C | VH | $$$ | P2 | category |
| عباية سوداء / black abaya | C | H | $$$ | P1 | `/ar/collections/black-abayas` [new] |
| عبايات مطرزة / embroidered abayas | C | M–H | $$$ | P1 | `/ar/collections/embroidered-abayas` [new] (matches "Embroidered Abaya") |
| عبايات كاجوال / casual abayas | C | M | $$ | P2 | collection [new] |
| عباية مفتوحة / open abaya | C | M | $$ | P2 | collection [new] |
| عبايات مقاس كبير / plus-size abayas | C | M | $$ | P2 | collection [new] |
| عبايات دوام/رسمية / work abayas | C | M | $$ | P2 | collection [new] |

### 2.2 Luxury abayas (core positioning)

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عبايات فاخرة / luxury abayas | C | H | $$$$ | **P0** | `/ar/collections/luxury-abayas` [new] (or subcategory) |
| عبايات فخمة | C | H | $$$ | P1 | same page (secondary keyword) |
| عبايات راقية | C | M | $$$ | P1 | same page |
| عبايات حفلات / evening abayas | C | M–H | $$$$ | P1 | `/ar/collections/evening-abayas` [new] |
| عباية بتطريز يدوي / hand-embroidered abaya | C | M | $$$ | P1 | embroidered collection |
| luxury abaya online Saudi Arabia | C | H | $$$ | P1 (EN) | `/en/collections/luxury-abayas` [new] |
| عباية نداء / كريب / حرير (fabric terms) | C | M | $$ | P2 | fabric collections + guide |

### 2.3 Saudi abayas

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عبايات سعودية | C | H | $$$ | P1 | `/ar/collections/saudi-abayas` [new] — brand-story page |
| عبايات سعودية 2027 (year modifier) | C | M | $$$ | P1 | same page, refreshed annually |
| عبايات سعودية للعيد | C | M | $$$ | P1 | Eid abayas seasonal page |
| بشت نسائي | C | M | $$ | P2 | collection if stocked |
| أفضل محلات عبايات في الرياض / جدة | CI | H | $$ | P2 | journal guide (§4.2) |
| Saudi abaya brands online | CI | M | $$ | P2 | journal (EN) |

### 2.4 Designer abayas

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عبايات مصممين / designer abayas | C | M–H | $$$ | P2 | `/ar/collections/designer-abayas` [new] |
| عبايات ديزاينر (transliterated) | C | M | $$$ | P2 | same |
| designer abayas online | C | H | $$$ | P2 (EN) | EN collection |
| عبايات إصدار محدود / limited-edition abayas | C | L–M | $$ | P2 | drop pages [new] |

### 2.5 Perfumes

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عطور / perfumes | C | VH | $$$$ | P3 | `/ar/category/perfumes` [exists] |
| عطور نسائية | C | VH | $$$$ | P2 | subcategory/collection |
| عطور عود / oud perfumes | C | H | $$$$ | P1 | `/ar/collections/oud-perfumes` [new] (matches "Royal Oud") |
| مسك أبيض / white musk | C | M | $$$ | P1 | product + collection (matches "White Musk") |
| دهن عود / عطور مركزة | C | H | $$$ | P2 | collection |
| عطور تدوم طويلًا | CI | H | $$$ | P1 | journal guide → collection |
| عطور أصلية | C | VH | $$$ | P1 | trust/authenticity page + category copy |

### 2.6 Luxury perfumes

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| عطور فاخرة | C | H | $$$$ | P1 | `/ar/collections/luxury-perfumes` [new] |
| عطور نيش / niche perfumes | C | H | $$$$ | P2 | collection |
| عطور شرقية فاخرة | C | H | $$$ | P1 | collection |
| luxury perfumes Saudi Arabia | C | M–H | $$$ | P1 (EN) | EN collection |
| best oud perfume for women | CI | M | $$$ | P2 | journal comparison |

### 2.7 Gift sets

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| طقم هدايا / gift sets | C | H | $$$ | P1 | `/ar/collections/gift-sets` [new] |
| بوكس هدايا / gift boxes | C | H | $$$ | P1 | `/ar/collections/gift-boxes` [new] |
| طقم عطور هدية | C | M | $$$ | P1 | collection |
| هدايا نسائية | C | H | $$$$ | P1 | `/ar/collections/gifts-for-her` [new] |
| سلة هدايا / gift basket | C | H | $$$ | P2 | collection |
| هدية بها بطاقة شخصية / personalised gift with message | C | L | $$ | P1 | gift-studio page [new] — **differentiator** |
| هدية مجهولة المرسل / anonymous gift | C | L | $$ | **P0** (uncontested) | `/ar/gifting/anonymous-gift` [new] — **differentiator** |

### 2.8 Women's fashion

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| ملابس نسائية | C | VH | $$$ | P3 | (not a near-term target) |
| أزياء نسائية فاخرة | C | H | $$$ | P2 | collection |
| ملابس محتشمة / modest fashion | C | H | $$$ | P2 | collection |
| شيلة / شيلات | C | M–H | $$ | P1 | accessories subcategory |
| أوشحة حرير / silk scarves | C | M | $$ | P1 | product/collection (matches "Silk Scarf") |
| women's fashion Saudi Arabia | C | H | $$ | P3 | (EN, long-term) |

### 2.9 Eid gifts

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| هدايا العيد / Eid gifts | C | H | $$$$ | **P0-seasonal** | `/ar/eid-gifts` (evergreen URL, refreshed yearly) [new] |
| هدايا عيد الفطر | C | H | $$$$ | P1 | section of Eid page |
| هدايا عيد الأضحى | C | M | $$$ | P2 | section |
| بوكس العيد / Eid box | C | M | $$$ | P1 | product/collection (matches "Eid Box") |
| عيدية | C | M | $$ | P2 | Eid page section |
| عبايات العيد / Eid abayas | C | H | $$$$ | **P0-seasonal** | `/ar/eid-abayas` [new] |
| هدايا رمضان / Ramadan gifts | C | H | $$$$ | **P0-seasonal** | `/ar/ramadan-gifts` [new] (matches "Ramadan Gift Set") |

### 2.10 Saudi gift delivery (gated by operations — current promise is "within 7 business days")

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| توصيل هدايا السعودية / gift delivery Saudi Arabia | C | M–H | $$$ | P1 | `/ar/gifting/delivery` [new] (real delivery times, cities, cut-offs) |
| ارسال هدية لشخص | C | M | $$$ | P1 | gifting hub [new] |
| توصيل هدايا الرياض / جدة / الدمام / مكة | C | H | $$$ | P2 | city pages **only if genuinely served with unique local value** |
| توصيل ورد الرياض | C | H | $$$ | P2 | flowers category + city page |
| توصيل هدايا نفس اليوم | C | H | $$$$ | **not viable today** | needs same-day operations first |

### 2.11 Luxury gifts

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| هدايا فاخرة | C | H | $$$ | P1 | `/ar/collections/luxury-gifts` [new] |
| هدايا راقية للنساء | C | M | $$$ | P1 | same |
| luxury gifts for her Saudi Arabia | C | M | $$$ | P2 | EN collection |
| هدايا شركات / corporate gifts | C | M–H | $$$$ (B2B) | P2 | `/ar/corporate-gifts` [new] |
| هدية للأم / للزوجة / للصديقة | CI | M–H | $$$ | P1 | recipient landing pages [new] |
| هدايا زواج / هدايا تخرج | C | H | $$$$ | P2 | occasion pages [new] |

### 2.12 Fashion accessories

| Keyword | Intent | KD | Value | Priority | Landing page |
|---|---|---|---|---|---|
| إكسسوارات نسائية | C | H | $$$ | P2 | `/ar/category/accessories` [exists] |
| حقائب يد نسائية | C | VH | $$$ | P3 | subcategory (matches "Handbag") |
| ساعات نسائية | C | VH | $$$ | P3 | subcategory (matches "Elegant Watch") |
| أوشحة وشيلان | C | M | $$ | P1 | subcategory |
| حزام / بروش عباية (abaya accessories) | C | L–M | $$ | P1 | subcategory — cross-sell complement to abayas |

### 2.13 Cross-cluster patterns to exploit

- **Modifiers Saudi shoppers add:** كود خصم, سعر, أفضل, تجارب/تجربتي, وين اشتري, اصلي, تقسيط, مدى, تابي/تمارا, مع التوصيل. Build content for **تجربة/مراجعة** and **اصلي** intent; register a permanent coupon/offers hub instead of leaving "كود خصم" to affiliate sites.
- **Payment trust queries:** mada, Apple Pay, Tabby, Tamara are configured in checkout — surface them in titles/meta and on-page (no COD, no STC Pay today; if COD is ever added it is a major Saudi modifier).
- **Year modifiers** ("2027") in titles for trend/collection pages; refresh, don't recreate, URLs.

---

## 3. Competitor analysis

**Caveat:** the list below comes from market knowledge, not a live SERP overlap export. Week-1 task: pull *Competing Domains* for the top 40 keywords in Ahrefs (KSA, Arabic) and confirm/replace.

| Segment | Likely competitors | What they win with | Aroma's opening |
|---|---|---|---|
| Marketplaces (head terms) | Noon, Namshi, Amazon.sa, Shein, Sivvi, 6thStreet | Sheer inventory, faceted navigation, domain authority, Shopping | Don't fight head terms. Win **curation, luxury and story**: unique descriptions, editorial, trust |
| Luxury / modest fashion | Ounass, Modanisa, Hanayen, Bouguessa (UAE luxury abaya houses), Farfetch/Net-a-Porter (designer) | Editorial content, lookbooks, brand authority, PR | Saudi-origin story, occasion-led collections (Eid, weddings, Founding Day), styling content in Arabic-first voice |
| Fragrance | Arabian Oud, Abdul Samad Al Qurashi, Ajmal, Rasasi, Lattafa, Goldenscent, Nice One, Sephora ME | Brand hubs, scent-family taxonomy, authenticity trust, huge review counts | Gift-ready perfume sets, scent guides (oud/musk/amber), authenticity page, brand hubs |
| Gifting & flowers | Floward (Saudi-founded gifting/flowers leader), Goldenscent gifting, Noon gifting | Occasion + recipient + **city** landing pages, same-day delivery, app-first | Personal-gift workflow (anonymous sender, signature, card) as a *content moat*; occasion pages; corporate gifting |

**Structural gaps to test (verify with a crawl of the top 5 competitors):**

- **Category structure:** leaders expose 3–4 levels (category → sub-category → attribute landings such as "black abayas", "oud perfumes for women") as indexable, uniquely copied pages. Aroma has one level and no collections.
- **Internal linking:** competitors interlink category ↔ guide ↔ brand ↔ product with keyword anchors; Aroma's links are limited to nav tiles and breadcrumbs (§1.3 #11).
- **Content gaps (highest confidence):** Arabic buying guides (fabrics, fit, oud vs musk), "عطور اصلية" trust content, gift-etiquette/occasion content, Ramadan/Eid evergreen hubs, "تجربة" review content — most Saudi stores publish little original Arabic editorial.
- **Backlink gaps:** measure with *Link Intersect* (competitors A+B+C but not Aroma). Expect the gap to be Arabic fashion/lifestyle media, Saudi startup/e-commerce press, wedding/event directories, and creator features (§8).
- **Keyword gaps:** run *Content Gap* on the same competitor set; export keywords where ≥2 competitors rank top-10 and Aroma doesn't; feed into the §2 tables.

**How Aroma outperforms them (plan, not slogan):**

1. **Specificity beats scale.** Own 150 precise commercial terms (black/embroidered/evening/Eid abayas, oud/musk perfumes, gift sets for her, anonymous gift) instead of chasing 5 head terms.
2. **Original Arabic-first content** where competitors machine-translate.
3. **Trust stack** (Maroof, CR/VAT, authenticity page, verified reviews, E-Authenticate seal, transparent returns) — the decisive conversion factor in Saudi gifting/fragrance.
4. **Feature-led differentiation:** the gift studio (message, signature, card, anonymous sender) is a real product moat; write pages and guides around it.
5. **Seasonal precision:** evergreen Ramadan/Eid/White Friday/National Day URLs published 8–10 weeks early and refreshed every year (competitors rebuild each season and lose the history).

---

## 4. Content strategy

### 4.1 Architecture (what to add)

| Layer | URL pattern | Purpose | Owner note |
|---|---|---|---|
| Category (exists) | `/{locale}/category/{slug}` | Broad commercial terms | Add 300–500-word SEO copy below the grid + FAQ |
| Sub-category | `/{locale}/category/{parent}/{child}` (model supports `parent_id`) | Mid-tail (e.g., embroidered abayas) | Use where the assortment justifies it (≥8 SKUs) |
| **Collections** | `/{locale}/collections/{slug}` | Curated, indexable attribute/occasion/recipient pages (black abayas, oud perfumes, gifts for her) | New page type: admin-curated product set + unique copy; governed indexable-facet policy (see below) |
| **Brand hubs** | `/{locale}/brand/{slug}` | Brand + product-type queries; `Brand` already has `meta_*` fields but no route | New route/view |
| **Seasonal hubs** | `/{locale}/eid-gifts`, `/ramadan-gifts`, `/white-friday`, `/national-day` | Evergreen URLs, refreshed annually | New; keep URL constant |
| **Gifting hub** | `/{locale}/gifting/…` (anonymous gift, personalised message, gift wrap, delivery) | Differentiator content | New |
| Guides (exists, 5) | `/{locale}/guides/*` | Buying/care/fit — expand to 15 | Localise URLs first (§1.3 #1) |
| **Journal** | `/{locale}/journal/{slug}` | Top/mid-funnel informational + comparison | New; `Article` schema, authors, dates |

**Indexable-facet policy (prevents index bloat):** index only *curated* attribute pages that have (a) demand evidence, (b) ≥6 products, (c) unique copy; everything else (`?sort`, `?price`, arbitrary combinations) stays non-indexable/robots-blocked.

### 4.2 Blog / Journal strategy

- **Voice:** Arabic-first (≈70% AR / 30% EN), written by Arabic copywriters/stylists — not translated. Brand archetype "The Lover" (per brand guidelines) → warm, sensory, precise.
- **Pillars (5):** دليل الشراء (buying guides) · الأزياء والستايل (style) · الإهداء (gifting) · عالم العطور (fragrance) · المناسبات (occasions).
- **Cadence:** 6/month months 1–3, 8–10/month afterwards (~85 pieces in 12 months, 55 AR / 30 EN).
- **Template:** 1,200–2,000 words · answer-first summary box · table (comparison/size) · product embeds with real links · FAQ · author bio with credentials · "last updated" · internal links (3 products, 1 category/collection, 2 related articles).
- **E-E-A-T:** named stylist/perfumer/“Aroma editorial team” page; photograph originals in-house; cite fabric/perfume-note sources.

### 4.3 Buying guides (first 10)

1. دليل شراء العباية الفاخرة: الأقمشة والقصّات والمقاسات
2. كيف تختارين العباية المناسبة لشكل جسمك (extend existing "Find your fit")
3. أنواع أقمشة العبايات: كريب، نداء، حرير، كتان، شيفون
4. دليل اختيار العطر: العود والمسك والعنبر
5. كيف تجعلين عطرك يدوم طويلًا
6. دليل الهدايا: هدية لكل مناسبة ولكل شخص
7. كيف تختارين هدية للأم / الزوجة / الصديقة / الزميلة
8. العناية بالعباية وتنظيفها (expand existing)
9. العطور الأصلية: كيف تتأكدين من أصالة العطر (trust; internal link from every perfume page)
10. كيف ترسلين هدية مجهولة المرسل في السعودية (gift-studio walkthrough)

### 4.4 Comparison articles

عود vs مسك vs عنبر · عباية كريب vs نداء vs حرير · Eau de Parfum vs Parfum vs Eau de Toilette · عباية مفتوحة vs مغلقة · هدية عطر أم عباية؟ · أفضل هدايا العيد لكل ميزانية (500 / 1,000 / 2,000 ريال) · Best oud perfumes for women (EN).

### 4.5 Seasonal engine (dates are approximate — confirm against the Umm al-Qura calendar)

| Season | Approx. date | Publish by | URL |
|---|---|---|---|
| White Friday | Nov 27, 2026 | Nov 1 | `/white-friday` |
| Ramadan | ~Feb 8, 2027 | Dec 15 | `/ramadan-gifts` |
| Founding Day | Feb 22 | Feb 1 | `/founding-day` |
| Eid al-Fitr | ~Mar 9–10, 2027 | Jan 20 | `/eid-gifts`, `/eid-abayas` |
| Mother's Day | Mar 21 | Feb 20 | `/mothers-day-gifts` |
| Graduation | May–Jun | Apr 15 | `/graduation-gifts` |
| Eid al-Adha | ~May 16–17, 2027 | Apr 25 | section of `/eid-gifts` |
| Summer weddings/travel | Jun–Aug | May 15 | collections |
| Back-to-school / teacher gifts | late Aug | Aug 1 | `/teacher-gifts` |
| Saudi National Day | Sep 23 | Sep 1 | `/national-day` |

Rule: **never delete or re-slug seasonal URLs.** Archive the offer, keep the page and its links, refresh the copy and the year.

### 4.6 12-month content calendar (Oct 2026 – Sep 2027)

Legend: **P** pillar · **C** cluster article · **G** guide · **X** comparison · **L** collection/hub launch · **R** PR/off-site.

| Month | Hook | Publish | Launch / commerce | Off-site |
|---|---|---|---|---|
| **Oct 2026** | Riyadh Season, winter-wedding season | P: دليل شراء العباية الفاخرة · G: اختيار العباية لشكل الجسم (rewrite) · C: أقمشة العبايات · C: عطور تدوم طويلًا · C: العطور الأصلية | L: luxury abayas, black abayas, oud perfumes · Trust pages live (CR/VAT/returns) | R: brand-story pitch to 10 outlets; Maroof + GBP + citations |
| **Nov 2026** | White Friday (Nov 27) | C: كيف تتسوقين في الجمعة البيضاء بأمان · X: EDP vs Parfum · C: هدايا الشتاء | L: `/white-friday` evergreen (live Nov 1) · Merchant Center feed live | R: creator seeding (Mawthooq-licensed) with tracked codes |
| **Dec 2026** | Ramadan prep (index lead time) | P: هدايا رمضان (live Dec 15) · C: عطور وبخور رمضان · C: عبايات رمضان · X: عود vs مسك vs عنبر | L: `/ramadan-gifts`, Ramadan gift sets | R: "Saudi gifting habits" data study outreach |
| **Jan 2027** | Ramadan / Eid prep | P: أحدث صيحات العبايات 2027 (trend report) · G: هدايا العيد لكل ميزانية · C: تغليف الهدايا · C: هدية مجهولة المرسل | L: `/eid-gifts`, `/eid-abayas` (live Jan 20) · Gift-studio hub | R: trend report → fashion magazines |
| **Feb 2027** | Ramadan starts (~Feb 8) · Founding Day (Feb 22) | C: أفكار هدايا يوم التأسيس · C: عبايات سهرات رمضان · G: هدايا عيد الأم · C: أفضل هدايا للزميلات | L: `/founding-day`, `/mothers-day-gifts` (live Feb 20) | R: influencer Ramadan campaign |
| **Mar 2027** | Eid al-Fitr (~Mar 9–10) · Mother's Day (Mar 21) | C: عبايات العيد الأنيقة · C: عيدية وهدايا الأطفال · G: الاستبدال بعد العيد · review-request campaign | Eid collections peak; post-Eid clearance hub | R: Eid gift-guide placements |
| **Apr 2027** | Post-Ramadan lull → evergreen build | P: دليل العطور (families & notes) · C ×4 perfume series · G: مقاسات العبايات (rewrite w/ chart) · technical audit #2 | L: brand hubs (all brands) · `/graduation-gifts` (live Apr 15) | R: link-intersect outreach wave 1 |
| **May 2027** | Eid al-Adha (~May 16–17) · Hajj · graduation | C: هدايا التخرج · C: عبايات الحج والعمرة · C: هدايا عيد الأضحى | Eid al-Adha section; graduation collection | R: partner content with event planners |
| **Jun 2027** | Summer weddings/travel | P: عبايات حفلات الزفاف · C: عبايات خفيفة للصيف · C: عبايات السفر · X: عباية مفتوحة vs مغلقة | L: wedding & travel collections | R: wedding-planner/venue partnerships |
| **Jul 2027** | Summer travel · mid-year | C: عطور صيفية منعشة · C: كيف تحفظين عطرك في الحر · H1 performance report (publish as data story) | L: summer edit · GCC-expansion architecture decision | R: PR recap + data study |
| **Aug 2027** | Back-to-school · teacher gifts | C: هدايا المعلمات · C: عبايات دوام أنيقة · G: 10 أسئلة قبل شراء عطر | L: `/teacher-gifts` (live Aug 1) · `/national-day` prep | R: link-building wave 2 |
| **Sep 2027** | Saudi National Day (Sep 23) | C: هدايا اليوم الوطني · C: عبايات باللون الأخضر · annual SEO review | L: `/national-day` (live Sep 1) · annual refresh of all seasonal URLs | R: annual PR/awards submissions |

---

## 5. On-page SEO templates

Titles ≤ 60 chars (Arabic text renders wide — check pixel width ≤ 580 px), meta ≤ 155 chars, keyword first, brand last. Only claim delivery/payment/returns that operations actually support.

### 5.1 Homepage

- **Title AR:** `أروما — عبايات فاخرة وعطور وهدايا | توصيل داخل السعودية`
- **Title EN:** `Aroma — Luxury Abayas, Perfumes & Gifts | Saudi Arabia`
- **Meta AR:** `تسوقي عبايات فاخرة وعطورًا وهدايا مغلّفة بعناية من أروما. ادفعي بمدى وآبل باي وتمارا وتابي، والتوصيل لمدن المملكة.`
- **Meta EN:** `Shop luxury abayas, fine perfumes and beautifully wrapped gifts at Aroma. Pay with mada, Apple Pay, Tabby or Tamara. Delivery across Saudi Arabia.`
- **H1:** `عبايات وعطور وهدايا فاخرة تُوقظ حواسك` / `Luxury Abayas, Perfumes & Gifts That Awaken Your Senses` (visible, above the fold, overlay on hero or directly beneath)
- **H2s:** تسوقي حسب الفئة · وصل حديثًا · الأكثر مبيعًا · فن الإهداء · لماذا أروما (trust: أصلي، تغليف، دفع آمن، استبدال) · أسئلة شائعة (3–5, visible) · من المجلة (3 latest articles)
- **Links:** hero CTA → luxury abayas; tiles → categories/collections; "من المجلة" → journal; footer trust links.

### 5.2 Category page

- **Title:** `{keyword} — {USP} | أروما` → `عبايات فاخرة للنساء — تصاميم حصرية | أروما` / `Luxury Abayas for Women — Exclusive Designs | Aroma`. Page N: append `— الصفحة N` / `— Page N`.
- **Meta:** `تسوقي {N}+ {keyword} من أروما: {خامات/تطريز/مناسبات}. {مدى/تابي/تمارا}، استبدال خلال {X} أيام، وتوصيل لمدن المملكة.` (use real numbers)
- **H1:** the keyword (`عبايات فاخرة`).
- **H2s:** الأكثر مبيعًا · تسوقي حسب المناسبة · تسوقي حسب الخامة · كيف تختارين {المنتج} (300–500 words) · أسئلة شائعة.
- **Internal links:** 6–8 top products as HTML links in the copy block; 3 sibling categories/collections; 2 guides; 2 brand hubs; breadcrumb.

### 5.3 Collection page

- **Title:** `{attribute/occasion keyword} {year} | أروما` → `هدايا رمضان 2027 — أفكار هدايا فاخرة | أروما`
- **H1:** the keyword; **H2s:** خيارات حسب الميزانية · حسب المستلمة (أم/زوجة/صديقة) · كيف تختارين · FAQ.
- **Links:** parent category, 2 guides, related collections, journal article.

### 5.4 Product page

- **Title:** `{Name} {Type/Material/Size} — {category noun} | أروما` → `عباية مطرزة كلاسيك — كريب فاخر | أروما` / `Royal Oud Eau de Parfum 100ml — Oud Perfume | Aroma`
- **Meta:** `{Name}: {material/notes}، {fit/size}. {price from} ر.س، غلاف هدية اختياري، {returns}. اطلبيها الآن من أروما.` — unique per product, 130–155 chars.
- **H1:** product name (+ type). **H2s:** الوصف · التفاصيل والمقاسات / نغمات العطر · العناية / الاستخدام · التوصيل والاستبدال · التقييمات · أسئلة شائعة · منتجات مشابهة.
- **Links:** category/collection, brand hub, 4–6 related, 1–2 guides (size/fragrance), gift-studio link.

### 5.5 Brand hub

- **Title:** `عطور {Brand} الأصلية — {N} عطر | أروما`; **H1:** `عطور {Brand}`; **H2s:** الأكثر مبيعًا · حسب النوع · عن {Brand} (200 words) · FAQ. Links to categories the brand appears in.

### 5.6 Guide / journal article

- **Title:** `{How-to/دليل} … ({year}) | أروما`; **H1:** same as title minus brand; **H2s:** answer-first summary · steps/criteria · comparison table · common mistakes · FAQ · related products. `Article` + `BreadcrumbList` schema; author + dates.

### 5.7 Static & utility

- About: `من نحن — قصة أروما | Aroma Gift Center` (founder story, CR/VAT, address, contact). Contact: real phone/WhatsApp. Policies: `noindex` not needed; unique titles. Cart/checkout/account/wishlist/login: `noindex,nofollow` meta **and** robots disallow.

### 5.8 Internal-linking rules (site-wide)

- Nav: Home · Abayas · Perfumes · Gifts · Eid/Seasonal (rotating) · Journal · About. Footer: category, collection, guide, trust columns.
- Every PDP → 1 category/collection + 1 brand hub + 4–6 related. Every article → 3 products + 1 collection + 2 articles.
- Anchor text: 50% partial-match, 30% exact-match (only for the target keyword), 20% branded/generic.
- Keep any page ≤ 3 clicks from home; link seasonal hubs from the home page 8–10 weeks before the season.

---

## 6. Arabic SEO strategy

### 6.1 How Saudis search (implications)

- **Mobile-first, Arabic-first.** The large majority of e-commerce queries are typed in Arabic on Android; English is significant among expats and in fashion/perfume brand names. Serve both, don't assume one.
- **Discovery starts on Snapchat/TikTok/Instagram/X; verification happens on Google.** Expect "brand + كود خصم / تجربتي / اصلي / وين اشتري" queries after a creator mention — the site must answer these directly.
- **Colloquial + MSA mix.** Queries lean colloquial (عباية, شيلة, بوكس) while product pages read as MSA. Use MSA for body copy, Gulf-natural vocabulary in titles/meta for CTR.
- **Gendered, personal phrasing** (اختاري, تسوقي) tests better with a female-majority audience; keep it consistent across nav, CTAs and meta.
- **City modifiers** (الرياض، جدة، الدمام، مكة، المدينة، الخبر) attach to delivery/gift queries.
- **Trust vocabulary** carries weight: اصلي, ضمان, استبدال, مدى, تقسيط, تابي, تمارا, معروف.

### 6.2 Technical rules for Arabic

- **Variants:** عباية/عباءة, عطر/عطور, هدية/هدايا, ورد/زهور: put the most-searched variant in H1/title, the other in the first 100 words and image alts. Google normalises hamza/tā' marbūṭa variants imperfectly.
- **Digits:** use Western digits (350) in prices/titles — dominant in Saudi search and matches schema; write "ريال" (or "ر.س") in prose and `SAR` in schema.
- **Titles/meta:** keyword first, brand last; no English tagline on Arabic pages (the current default meta for Terms shows "Awaken your Senses" on an Arabic page).
- **Transcreation, not translation.** Arabic pages must be written natively; literal EN→AR output reads flat and underperforms. Keep an Arabic style guide (tone: warm, feminine, aspirational, no slang).
- **RTL correctness:** `dir="rtl"`, logical CSS properties (already used), correct `lang="ar"`; test shaping in Safari (the trial AligarhArabic font is already patched for it).
- **Fonts:** prefer local WOFF2 Arabic subsets to Google Fonts round-trips.

### 6.3 Ranking for Arabic commercial searches

1. Category and collection pages carry **300–500 words of native Arabic copy** with the keyword in H1, first paragraph, one H2, and image alts.
2. Each product has a **unique 200+ word Arabic description** (§7) — the single strongest differentiator against translated marketplace listings.
3. Publish **Arabic buying guides** for every commercial cluster and interlink them with keyword anchors.
4. Build **Arabic third-party signals:** Arabic media coverage, Arabic creator mentions, Maroof reviews, Google reviews written in Arabic.
5. **Arabic FAQs** phrased as people ask them (هل العباية تتقلص بعد الغسيل؟ كم تدوم رائحة العود؟) — also the format AI answers prefer.
6. Track rankings **in Arabic, from a Saudi location, on mobile** (Ahrefs/AccuRanker with Riyadh + Jeddah).

### 6.4 Arabic category naming (change what's generic)

| Current | Problem | Recommended (AR) | Recommended (EN) |
|---|---|---|---|
| العطور | fine | **عطور** (keep) + sub: عطور عود · مسك · عطور نسائية | Perfumes |
| العبايات | fine, generic | **عبايات فاخرة** as a child/collection; keep العبايات as parent | Abayas / Luxury Abayas |
| الزهور والهدايا | two intents in one | split: **ورد وباقات** and **هدايا** | Flowers & Bouquets / Gifts |
| الجمال | ambiguous (beauty vs. skincare vs. makeup) | **العناية والجمال** (or split مكياج / عناية) | Beauty & Care |
| الإكسسوارات | fine | **إكسسوارات نسائية** with children: أوشحة وشيلان · حقائب · ساعات | Women's Accessories |
| المنتجات الموسمية | no search demand | rotate real season names: **مجموعة رمضان / العيد / اليوم الوطني** | Seasonal → named seasonal hubs |

---

## 7. Product SEO standards

**Titles:** `{Name} {Type} {Key attribute (material/ml/size/colour)} — {category noun}`; ≤ 60 chars in `<title>`, fuller in H1. Avoid ALL-CAPS and keyword stuffing.

**Descriptions (mandatory before launch; currently 74 chars, identical):**
- AR and EN, **unique per SKU**, 200–350 words; 1 paragraph story, then structured blocks:
  - **Abayas:** fabric & weight, cut/silhouette, sleeve/embroidery detail, colour, lining, closure, care, sizing note + size-chart link, occasions (دوام/سهرة/عيد), styling tip.
  - **Perfumes:** concentration, volume, **top/heart/base notes**, longevity/sillage, season/time, occasion, "is it a gift?" note, authenticity statement.
  - **Gifts:** contents list, dimensions, wrapping/greeting-card options, personalisation, occasion, delivery note.
- Short description ≤ 160 chars (drives meta fallback); add 3–5 visible FAQs.
- Enforce in admin validation: minimum length, uniqueness check, and a "SEO completeness" badge.

**Meta:** `meta_title`/`meta_description` fields already exist — render them (fallback to template). Unique per SKU; no duplicates across the catalog.

**Images:**
- ≥ 6 per product: front, back, detail, lifestyle, packaging, size/scale (+ short video for hero SKUs).
- 4:5 master at ≥ 1200×1500; auto-generate WebP/AVIF + JPEG fallback at 480/768/1080/1600; explicit `width`/`height`; `loading="lazy"` except the first.
- Filenames: `royal-oud-eau-de-parfum-100ml-front.webp`; lowercase, hyphenated, English (slugs) — Arabic in alt.
- **Alt text:** descriptive, unique, per locale, ≤ 125 chars, no stuffing: `عباية سوداء مطرزة بأكمام واسعة — منظر أمامي` / `Black embroidered abaya with wide sleeves — front view`. The `ProductImage` model already supports translated `alt`; make it required in admin.
- Placeholder SVG must never appear as `og:image` or schema `image`.

**Product schema (complete):** name, `image[]`, full `description`, `sku`, `mpn`/`gtin` when available, `brand`, `color`/`material` (abayas), `Offer` (`price`, `priceCurrency: SAR`, `availability`, `itemCondition`, `priceValidUntil`, `url`, `seller`), `shippingDetails` (delivery time, cost, KSA destination), `hasMerchantReturnPolicy` (match the published policy and Saudi e-commerce rules), variants via `ProductGroup`/`hasVariant` or `AggregateOffer`, `aggregateRating` + `review[]` **only** for reviews visible on the page. Test in Rich Results Test and the Merchant Center diagnostics.

**Reviews schema:** collect verified-purchase reviews by email 7–10 days post-delivery (email pipeline exists), in Arabic; show the review text on-page; never mark up reviews of the business itself as product reviews.

**FAQ schema:** mark up the visible FAQ on PDP/PLP/guides (3–5 Qs). No SERP rich result is expected (see §1.6 caveat) but it improves machine extraction and AI citation.

**Lifecycle:** out of stock → keep 200 with `OutOfStock` + related products; permanently discontinued → 301 to the closest category/collection; removed with no replacement → 410; variants share one canonical URL.

---

## 8. Link-building strategy (white-hat only)

**Principles:** quality > volume; Arabic-language and Saudi-relevant referring domains first; every link earned by a real asset (data, product, story, partnership); all paid/gifted creator links carry `rel="sponsored"`.

**Assets to create first (linkable):**
1. **"Saudi Gifting Habits Report"** (annual survey/data study; Eid, National Day, budgets by relation).
2. **Abaya Trend Report** (each Jan/Jul) with original photography and fabric/colour data.
3. **Perfume Guide** (families/notes, oud grading) — a reference asset for bloggers and journalists.
4. Interactive tools: abaya size calculator; "find the perfect gift" quiz (feeds the gift studio).

**Tactics:**

| Channel | Targets | Approach |
|---|---|---|
| **PR / fashion & lifestyle media** | Vogue Arabia, Harper's Bazaar Arabia, Elle Arabia, Cosmopolitan Middle East, Grazia ME, Sayidaty (سيدتي), Hia (هيا) | Saudi-origin brand story; trend/data reports; seasonal gift guides pitched 8–10 weeks ahead |
| **Business / startup media** | Wamda, Arabian Business, Entrepreneur Middle East, Saudi e-commerce outlets | Founder story, e-commerce milestones, gifting-tech (gift studio) angle |
| **Creators / influencers** | Saudi fashion, beauty and lifestyle creators on Snapchat, TikTok, Instagram, X | Engage only **Mawthooq-licensed** creators (GCAM); tracked codes + UTM links; request an editorial link/blog mention, not just a story; disclose (#إعلان) |
| **Saudi directories & trust** | **Maroof (معروف)**, Saudi Business Center profile (E-Authenticate already in place), chambers of commerce, Saudi Yellow Pages, Bing Places, Apple Maps | Free/low-cost, high trust; NAP consistency |
| **Partnerships** | Wedding planners, event/venue operators, hotels/spas (Riyadh Season), corporate-gifting buyers, florists, charities (e.g., Ehsan campaigns) | Co-branded gift boxes; resource-page and "partners" links; corporate-gifting landing page |
| **Digital PR "newsjacking"** | Ramadan, Eid, Founding Day, National Day | Prepared expert quotes/data available before the season |
| **Resource / broken-link building** | Arabic bridal, lifestyle, Umrah-travel and gift blogs | Offer the size guide / perfume guide as a replacement for dead links |
| **Brand-supplier links** | Perfume and fabric suppliers/brands | "Stocked at Aroma" links from official brand pages |

**Targets (planning):** 25–40 referring domains by month 6, 80–120 by month 12; ≥ 60% Arabic/Saudi-relevant, DR 30+ for at least 30% — validate against competitor link profiles (§3). No PBNs, paid links, or mass directory submissions.

---

## 9. Local SEO

Aroma is an online store, so local SEO is a **trust and entity** play, not a map-pack play — unless there is a showroom, pickup point or studio.

1. **If any physical location exists:** claim a Google Business Profile (Arabic + English names; primary category "Gift shop"/"Women's clothing store"; National Address short code; hours; photos; WhatsApp link; products). Post weekly seasonal updates; collect Arabic reviews. If none exists, do **not** create a fake or service-area listing.
2. **Maroof (معروف):** register and keep the rating healthy — heavily trusted by Saudi shoppers; link from the footer and Organization `sameAs`.
3. **NAP + legal consistency:** identical business name ("Aroma Gift Center"), address/National Address code, phone, email, CR and VAT numbers across site, Google, Maroof, social profiles and directories.
4. **City pages** (Riyadh, Jeddah, Dammam/Khobar, Makkah, Madinah): only where operations genuinely deliver, and each with unique value — real delivery times/cut-offs, city-specific gift ideas/occasions, local FAQs. Otherwise they are doorway pages and will be discounted. "Same-day" keywords are unavailable until operations support them (current promise: 7 business days).
5. **Local link/citation targets:** Saudi wedding/event directories, chamber listings, local lifestyle bloggers.
6. **Local schema:** `Organization.areaServed: SA`; add `Store`/`LocalBusiness` only with a real location.

---

## 10. AI search optimisation (ChatGPT, Google AI Overviews, Gemini, Perplexity)

**What decides citation:** each engine cites pages it can crawl, that answer the question in the first lines, and whose entity/brand it can verify from multiple sources. Google AI Overviews and Gemini draw on Google's index (organic ranking is the entry ticket); ChatGPT search leans partly on Bing's index plus its own crawler; Perplexity uses its own crawler plus Bing/Google signals. Program availability for shopping features in KSA changes quickly — verify current status.

**Actions:**

1. **Crawl access.** Keep `robots.txt` open to search-oriented AI crawlers (OAI-SearchBot, PerplexityBot, Googlebot, Bingbot, ClaudeBot). If IP policy demands opting out of *training*, do it via `Google-Extended`/`GPTBot`, which does not remove you from AI *search*.
2. **Bing + IndexNow.** Register Bing Webmaster Tools and add IndexNow — this feeds Bing's index and hence ChatGPT/Copilot visibility. Submit sitemaps to Google and Bing.
3. **Answer-first formatting.** Every guide, PLP and PDP opens with a 40–60-word direct answer/summary; then structured detail. Use HTML tables (not images) for comparisons, sizes, notes; short Q&A blocks in natural Arabic and English.
4. **Entity building.** Consistent brand name "Aroma Gift Center/أروما"; Organization schema with `sameAs` to every real profile; an About page with verifiable facts (founding, location, CR, categories, delivery); Wikidata entry if notability criteria are met; consistent Arabic/English naming.
5. **Third-party corroboration.** AI models weight independent mentions: Arabic media coverage, Maroof/Google reviews, creator features, brand-supplier pages. This overlaps §8 — one program, two payoffs.
6. **Merchant Center + product feeds.** Complete, accurate feeds (price, availability, GTIN/MPN, shipping, returns, images) power Google Shopping/AI Mode surfaces and are the input format used by emerging agent-commerce programs.
7. **Freshness signals.** `dateModified`, visible "updated {month year}", stable URLs for evergreen hubs.
8. **Original, citable data.** The gifting-habits and trend reports (§8) are exactly what LLMs quote.
9. **`llms.txt` (optional, low confidence).** No major engine has confirmed using it; cheap to add, don't count on it.
10. **Measurement.** Manually test a panel of 50 prompts (AR/EN, e.g. "أفضل عبايات فاخرة في السعودية", "best oud perfume gift for her Saudi Arabia") monthly across ChatGPT, Gemini, Perplexity and Google AI Overviews; log mention, citation, sentiment. Track referrals from `chatgpt.com`, `perplexity.ai`, `gemini.google.com`, `copilot.microsoft.com` in GA4 (custom channel group) and consider an AI-visibility tool (Ahrefs Brand Radar, Semrush AI toolkit).

---

## 11. Revenue impact

### 11.1 Initiative register (scores 1–5; effort 1 = least work)

| ID | Initiative | Traffic | Revenue | Effort | Phase |
|---|---|---|---|---|---|
| I-01 | Locale-prefix static pages + hreflang + 301s | 4 | 2 | 2 | 30d |
| I-02 | Homepage H1 + title/meta/OG; unify AR/EN positioning | 3 | 4 | **1** | 30d |
| I-03 | Render `meta_title`/`meta_description`; enforce unique copy | 4 | 4 | **1** | 30d |
| I-04 | OG/Twitter images (brand default + per-product raster) | 2 | 3 | **1** | 30d |
| I-05 | Pagination canonical + facet/`/locale/` robots policy | 3 | 2 | **1** | 30d |
| I-06 | Sitemap v2 (index, hreflang, real lastmod, images, guides) | 3 | 2 | 2 | 30d |
| I-07 | Structured data v2 (Product/Org/WebSite) | 3 | 4 | 2 | 30–60d |
| I-08 | Internal linking rebuild (nav, footer, related, brand hubs) | 4 | 4 | 2 | 30–60d |
| I-09 | **Product content sprint** (copy, photos, size/scent data) | 5 | 5 | 4 | 30–90d |
| I-10 | Category SEO copy + collection pages for top 10 targets | 5 | 5 | 3 | 30–90d |
| I-11 | Performance sprint A (defer libs, fonts, image dims/WebP) | 3 | 4 | 3 | 30–60d |
| I-12 | Performance sprint B (edge cache, session-less GET, PHP/Laravel upgrade) | 3 | 3 | 4 | 90d–6m |
| I-13 | Site search (+ `SearchAction`) | 3 | 4 | 3 | 60–90d |
| I-14 | Measurement stack (GSC, GA4 ecommerce, Bing, Merchant Center, Clarity) | 1 | 4 | 2 | **Week 1** |
| I-15 | Trust layer (CR/VAT/contact, Maroof, review programme) | 2 | 4 | 2 | 30d |
| I-16 | Journal + 24 articles by month 6 | 5 | 4 | 3 | 60d–6m |
| I-17 | Guides 5 → 15 | 3 | 3 | 2 | 60–120d |
| I-18 | Gifting differentiator pages (anonymous gift, card, wrap, delivery) | 3 | 4 | 2 | 30–60d |
| I-19 | Seasonal engine (evergreen Ramadan/Eid/White Friday/National Day) | 5 | 5 | 2 | 60d → ongoing |
| I-20 | Google Merchant Center free listings + Shopping | 3 | 4 | 2 | 30–60d |
| I-21 | Digital PR + magazines | 3 | 3 | 3 | 60d → ongoing |
| I-22 | Creator programme (Mawthooq, tracked codes, sponsored links) | 2 | 4 | 3 | 60d → ongoing |
| I-23 | Citations, Maroof, directories, GBP (if physical) | 1 | 2 | **1** | 30d |
| I-24 | Partnerships (planners, hotels, corporate gifting) | 2 | 3 | 3 | 90d → ongoing |
| I-25 | AI-search readiness (Bing/IndexNow, entity, answer-first) | 2 | 3 | 2 | 30–90d |
| I-26 | GCC expansion architecture (`/ae`, `/kw` + hreflang) | 4 | 4 | 4 | 6–12m |
| I-27 | Brand hubs `/brand/{slug}` | 3 | 3 | 2 | 60d |
| I-28 | Non-prod noindex safeguard | 1 | 1 | **1** | Week 1 |
| I-29 | Reviews/UGC at scale | 2 | 4 | 2 | 60d → ongoing |
| I-30 | Governed programmatic collections (colour/fabric/occasion) | 4 | 4 | 3 | 90d–6m |

### 11.2 Rankings

**Highest traffic impact:** I-09, I-10, I-16, I-19, I-26, I-30, I-01, I-08, I-03, I-17.
**Highest revenue impact:** I-09, I-10, I-19, I-02, I-03, I-07, I-08, I-15, I-13, I-20.
**Lowest effort (do first):** I-28, I-02, I-03, I-04, I-05, I-23, I-14, I-06, I-15, I-18.

### 11.3 Impact by horizon

**Quick wins (30 days) — enable, protect, and lift CTR.**
- Indexation of English/guide pages, correct titles/meta on 24+ URLs, working link previews on WhatsApp/X, cleaner crawl, measurement live.
- Expected: CTR lift of ~10–25% on branded/category impressions once real titles/meta are rendered (typical for keyword-led vs. brand-only titles); zero-to-first non-brand impressions within 3–6 weeks of indexing. **Absolute revenue in this window is small for a new domain; the value is unblocking everything after it.**

**Medium term (90 days) — first ranking clusters.**
- Content sprint complete (≥ 40 SKUs with unique copy/photos), 10 collection pages, 12+ journal/guide pieces, Merchant Center live, first 15–25 referring domains, review programme collecting.
- Expected: page-1 for brand + a first tranche of low/medium-KD long-tail terms; Shopping/Images impressions begin; seasonal hub (Ramadan) indexed ahead of season.

**Long term (6–12 months) — authority and compounding.**
- Ramadan/Eid peak captured with 8–10-week lead; 80–120 referring domains; 85 journal/guide assets; performance CWV pass; GCC subfolder decision; AI-citation presence for niche prompts.
- Expected: top-3 for 40–60 long-tail commercial terms (months 6–9), first head-term movement (top-10 for "عبايات فاخرة"-class terms) in months 12–18.

### 11.4 Planning scenarios (assumptions explicit; replace with GSC/GA4 actuals after ~8 weeks)

Assumptions: blended organic conversion 1.2% / 1.8% / 2.5% (conservative/base/aggressive — Saudi mobile-heavy e-commerce norms), blended AOV ≈ SAR 380 (abayas SAR 450–700, perfumes 250–450, gifts 200–350; aggressive uses SAR 400). Sessions are *organic, per month*.

| Month | Conservative sessions → revenue | **Base** sessions → revenue | Aggressive sessions → revenue |
|---|---|---|---|
| 3 | 1,000 → SAR 4.6k | **2,500 → SAR 17k** | 5,000 → SAR 50k |
| 6 | 5,000 → SAR 23k | **12,000 → SAR 82k** | 25,000 → SAR 250k |
| 9 | 12,000 → SAR 55k | **28,000 → SAR 192k** | 60,000 → SAR 600k |
| 12 | 25,000 → SAR 114k | **55,000 → SAR 376k** | 110,000 → SAR 1.1M |

Read these as directional envelopes tied to executing §1–§8; they assume real inventory with unique content, photography, working delivery/returns, and seasonal timing. If the catalog stays placeholder-grade, expect the conservative case *or worse* regardless of technical fixes.

---

## 12. Deliverables

### 12.1 Prioritised SEO roadmap

| Phase | Goal | Contents (by initiative) |
|---|---|---|
| **Foundation (Weeks 1–4)** | Make the site indexable, coherent and measurable | I-14, I-28, I-02, I-03, I-04, I-05, I-01, I-06, I-15, I-23 |
| **Content & Structure (Weeks 5–12)** | Give Google something to rank; give users reasons to buy | I-09, I-10, I-07, I-08, I-11, I-18, I-27, I-20, I-25 |
| **Growth (Months 4–6)** | First clusters + authority | I-16, I-17, I-19 (Ramadan/Eid), I-13, I-21, I-22, I-29, I-12 |
| **Scale (Months 7–12)** | Own the niche, prepare GCC | I-24, I-30, I-26, annual seasonal refresh, link waves 2–3 |

### 12.2 30-day action plan

**Week 1 — instrument and de-risk**
- Verify the domain in Search Console (Domain property + URL-prefix for `/ar/`, `/en/`), Bing Webmaster Tools; GA4 with ecommerce events (§12.6); Merchant Center account; Microsoft Clarity. *(I-14)*
- Add non-production `noindex`/HTTP auth safeguard. *(I-28)*
- Pull keyword volumes/KD for §2 in Keyword Planner + Ahrefs/Semrush (KSA); competitor Competing-Domains + Content-Gap + Link-Intersect exports. *(§3)*
- Run PageSpeed Insights (mobile) on 5 templates for a real baseline.
- Confirm production canonical host/scheme/redirects (`https`, `www`, `TrustProxies`, `forceScheme`).

**Week 2 — quick code wins**
- Homepage H1, title/meta, unify AR/EN hero subtitle; brand disambiguation ("Aroma Gift Center"). *(I-02)*
- Render `meta_title`/`meta_description` on product/category/brand; fix Terms fallback; admin min-length validation. *(I-03)*
- OG/Twitter: branded 1200×630 default; per-product raster; block SVG fallback. *(I-04)*
- Pagination self-canonical + titles; robots rules for facets and `/locale/`; `max-image-preview:large`. *(I-05)*

**Week 3 — architecture**
- Move `/about`, `/guides/*`, `/contact`, `/terms`, `/privacy-policy` under `/{locale}/…`; 301 legacy URLs; hreflang; switcher links point to real locale URLs. *(I-01)*
- Sitemap v2: index + hreflang + honest `lastmod` + guides + images. *(I-06)*
- Trust layer: CR/VAT/address/phone/WhatsApp/social in footer + Organization schema; Maroof registration; citations. *(I-15, I-23)*

**Week 4 — content kickoff**
- Finalise product-content template and start the sprint on the top 20 SKUs (AR+EN copy, 6 photos each, size/notes data). *(I-09)*
- Write SEO copy for the 6 categories; publish 2 collections (luxury abayas, oud perfumes). *(I-10)*
- Structured data v2 for Product/Organization/Breadcrumb; validate. *(I-07)*
- Draft the Ramadan hub and Journal architecture; brief the first 6 articles.

### 12.3 90-day action plan (Weeks 5–13)

1. **Content:** complete ≥ 40 SKUs; 10 collections; 12 journal/guide pieces (buying guides 1–5, fabrics, authenticity, anonymous-gift how-to); expand guides to 10. *(I-09, I-10, I-16, I-17, I-18)*
2. **Internal linking:** nav/footer rebuild, related-products module, brand hubs, in-copy links. *(I-08, I-27)*
3. **Performance sprint A:** conditional library loading, font trimming, self-hosted Bootstrap, WebP/srcset pipeline with dimensions, hero preload, intro splash shortened; re-measure. *(I-11)*
4. **Search:** ship site search with Arabic normalisation + `SearchAction`. *(I-13)*
5. **Merchant Center & AI readiness:** feed live, IndexNow, entity/`sameAs`, answer-first templates. *(I-20, I-25)*
6. **Authority:** 15–25 referring domains (Maroof, directories, first PR wave, 5–8 creator features); reviews programme live (target ≥ 30 verified reviews). *(I-21–I-23, I-29)*
7. **Seasonal:** Ramadan hub live by Dec 15 (per §4.5), then Eid hubs by Jan 20. *(I-19)*
8. **Review:** 90-day audit — indexed vs. submitted, non-brand clicks by cluster, CWV, orders by landing page; re-rank §2 by real data.

### 12.4 12-month growth roadmap

| Quarter | Focus | Milestones |
|---|---|---|
| **Q1 (Oct–Dec 2026)** | Foundation + first content + White Friday/Ramadan prep | Technical fixes live; 40+ SKUs; 10 collections; 15+ articles; White Friday hub; Ramadan hub live Dec 15; 25 referring domains |
| **Q2 (Jan–Mar 2027)** | Ramadan/Eid peak | Eid hubs live Jan 20; peak-season capture; creator campaign; review programme scaling; Merchant Center Shopping; CWV pass |
| **Q3 (Apr–Jun 2027)** | Evergreen depth + authority | Perfume guide; brand hubs; graduation/Eid al-Adha/wedding content; link waves 1–2; PHP/Laravel upgrade + edge caching; governed programmatic collections |
| **Q4 (Jul–Sep 2027)** | Scale + expansion decision | Data reports (H1 trends); back-to-school + National Day; 80–120 referring domains; GCC subfolder decision (UAE/Kuwait) with local currency/shipping; annual seasonal refresh; annual SEO review |

### 12.5 KPI dashboard (Looker Studio; weekly review, monthly exec view)

| Layer | KPI | Source | Target (month 12, base) |
|---|---|---|---|
| Visibility | Non-brand impressions, clicks, CTR, avg position **by cluster** (§2) | GSC | +20× vs. month 1; CTR ≥ 3% on P0/P1 terms |
| Visibility | Share of voice vs. 5 competitors (top-100 keyword set) | Ahrefs/Semrush | rank ≥ 3 in niche set |
| Visibility | Top-3 / top-10 keyword counts | rank tracker (Riyadh, mobile, AR+EN) | 40–60 top-3, 150+ top-10 |
| Index health | Indexed / submitted; excluded reasons | GSC | ≥ 95% of intended URLs; 0 unintended facets |
| Technical | CWV pass rate (LCP ≤ 2.5 s, INP ≤ 200 ms, CLS ≤ 0.1, p75 mobile) | CrUX/GSC | ≥ 90% of URLs "Good" (internal target LCP ≤ 2.0 s) |
| Technical | Crawl errors, 5xx, redirect chains, sitemap freshness | GSC + server logs + Screaming Frog (scheduled) | 0 critical |
| Commerce | Organic sessions, users, new vs. returning | GA4 | see §11.4 |
| Commerce | Organic conversion rate, AOV, revenue, revenue/session | GA4 (ecommerce) | 1.8% / SAR 380 |
| Commerce | Assisted conversions from organic; landing-page revenue | GA4 | — |
| Content | Articles/collections published; % with unique copy; pages with ≥ 3 internal links | CMS + crawl | 100% of SKUs unique |
| Authority | Referring domains (total, Saudi/Arabic, DR 30+); link velocity | Ahrefs | 80–120 |
| Brand | Branded search volume/impressions ("أروما", "Aroma Gift Center") | GSC + Trends | ↑ every quarter |
| AI | AI referrals; monthly prompt-panel mentions/citations | GA4 channel group + manual panel | present in ≥ 20% of niche prompts |
| Trust | Verified reviews (count, avg), Maroof rating | site + Maroof | ≥ 200 reviews; ≥ 4.5 |

### 12.6 Tracking setup

- **Search Console:** Domain property + URL-prefix properties `/ar/` and `/en/`; submit sitemap index; enable email alerts; use URL Inspection + Request Indexing for launch URLs.
- **Bing Webmaster Tools + IndexNow;** Merchant Center (free listings first, then Shopping ads).
- **GA4:** ecommerce events — `view_item_list`, `select_item`, `view_item`, `add_to_wishlist`, `add_to_cart`, `view_cart`, `begin_checkout`, `add_shipping_info`, `add_payment_info`, `purchase` (`transaction_id`, `value`, `currency: SAR`, `items` with `item_category`/`item_brand`). Custom dimensions: locale, gift-studio used (anonymous/personalised), payment method, coupon. Custom channel group for **AI referrals**. Link GA4 ↔ GSC ↔ Google Ads.
- **Consent:** Consent Mode v2 + cookie banner aligned with KSA's Personal Data Protection Law (PDPL).
- **Server-side purchase events** (GTM server container or Conversions API) to survive ad blockers; Snapchat, TikTok and Meta pixels for creator-attribution reconciliation.
- **Attribution hygiene:** UTM standard for creators/PR (`utm_source=creator_name&utm_medium=influencer&utm_campaign=ramadan27`); unique coupon codes per creator mapped to orders; landing-page-level revenue reporting.
- **Behaviour:** Microsoft Clarity (free heatmaps/recordings) on PLP/PDP/checkout; review Arabic search terms if site search ships.
- **Monitoring:** scheduled Screaming Frog/Sitebulb crawl (weekly), PageSpeed/CrUX API monitor, uptime alerting, log-file review (Googlebot/Bingbot/AI-bot hits, wasted crawl on params).
- **Rank tracking:** Riyadh + Jeddah, mobile, Arabic + English, ~300 keywords from §2, competitor overlay.
- **Reporting cadence:** weekly ops dashboard; monthly SEO review (actions, wins, blockers); quarterly strategy reset against §11.4 scenarios.

---

## Appendix A — Engineering backlog mapped to this codebase

| Item | Where |
|---|---|
| Locale-prefix static pages, 301 legacy | `routes/web.php` (lines 168–180 → inside the `{locale}` group); `pages/*` views use `$isAr` → use `app()->getLocale()` from the route |
| hreflang for new routes | already automatic via `App\Support\Seo::alternateUrls()` for any route with `{locale}` |
| Homepage H1, title/meta, hero subtitle | `resources/views/home/index.blade.php`; `resources/lang/{ar,en}/storefront.php` (`hero.*`) |
| Render `meta_title` + fallbacks | `catalog/product.blade.php` line 3–4, `catalog/category.blade.php` line 3–4, brand hub view; admin validation in `Admin\*Controller` requests |
| OG image defaults | `layouts/app.blade.php` (`og_image` yield), product view line 6 (`primaryImageUrl()` returns SVG) |
| Pagination canonical/title | `layouts/app.blade.php` canonical (`url()->current()`); category view (`request('page')`) |
| Robots | `routes/web.php` `robots.txt` closure (add patterns; env-aware `Disallow: /`) |
| Sitemap v2 | `App\Http\Controllers\SitemapController`, `resources/views/sitemap.blade.php` |
| Structured data v2 | `App\Models\Product::toSchemaOrgArray()`, `ProductController::breadcrumbSchema()`, `layouts/app.blade.php` graph |
| Nav/footer links | `layouts/partials/header.blade.php` (lines 99–103 both point to `#categories`), `layouts/partials/footer.blade.php` |
| Conditional heavy libs | `layouts/app.blade.php` lines 141–152, 185–196 → move to `@push` stacks on checkout/auth/forms |
| Image pipeline | new service beside `App\Support\HeroImageProcessor`; hook into `Admin\ProductController` upload |
| Site search | `HomeController`/`CatalogService` (no `q` handling today) + header form |
| Proxy/HTTPS | `app/Http/Middleware/TrustProxies.php`, `AppServiceProvider` (`URL::forceScheme`) |
| Platform upgrade | `composer.json` (`laravel/framework ^8.75`), PHP 7.4 → 8.2+ |
| Stale `theme-color` | `layouts/app.blade.php` line 50 (`#704F2F` → `#330101`) |
