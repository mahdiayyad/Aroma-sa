# Aroma — SEO strategy

Based on the production export of **21 Sep 2026** (`aroma-products-20260921-143245.xlsx`): 6 products, all in one category
(ABAYA), all active. The ready-to-import copy is in [`aroma-products-seo-ready.xlsx`](aroma-products-seo-ready.xlsx).

> **What this is and isn't.** Recommendations come from your catalogue and the storefront code. I have no access to production,
> Search Console or keyword-volume data, so nothing below quotes search volumes or rankings. Keyword choices follow how Saudi
> shoppers typically phrase abaya searches and must be checked against your own Search Console data (§6).

---

## 1. Where you stand

| | Finding | Why it matters |
|---|---|---|
| ✅ | Every product has a unique, well-written Arabic **and** English description and short description | Unique copy in both languages is the hardest part; you have it |
| ✅ | Clean English slugs (`rose-crystal-abaya`), sitemap, robots, canonical, hreflang, Product/Breadcrumb/Organization JSON-LD, verified-store seal | Technical foundations are in place |
| ❌ | **All meta titles, meta descriptions and keywords are empty** (0/6) | Titles fall back to `name — Aroma` (Arabic pages: `عباية روز كريستال — Aroma`, Latin brand in an Arabic title) and snippets to the short description. No product-type keyword, no colour/occasion phrasing |
| ❌ | **One image per product**, file names like `zsvKmSfExx….png` (some PNG) | Weak for Google Images and page speed; no descriptive names, one angle only |
| ❌ | **No brand assigned** (0/6) | The Product schema only includes `brand` when one is set |
| ⚠️ | Descriptions never mention **fabric, length/sizes or care** | Shoppers search "عباية كريب", "عباية ستان", "مقاس"; these are also the questions that stop a purchase |
| ⚠️ | One category, named **"ABAYA"** (singular, upper-case) | The category page is the page that should rank for the head term (عبايات / abayas) |
| ⚠️ | Not featured (0/6), no sort order, all "New arrival" | Homepage/category merchandising signal is flat; "new" on everything means nothing |
| ⚠️ | 6 products is a thin catalogue for topical authority | Content has to carry more of the SEO load (§4, P3) |

---

## 2. What's ready to ship now

[`aroma-products-seo-ready.xlsx`](aroma-products-seo-ready.xlsx) is **your export with only 5 columns filled** for each product —
Meta Title (AR / EN), Meta Description (AR / EN), Meta Keywords. 30 cells changed; SKU, names, descriptions, prices, stock, slugs,
status, flags and image links are byte-identical (checked cell by cell).

**Tested** against the real importer in an isolated environment: review shows **0 new · 6 updated · 0 unchanged, no errors**; after
confirming, prices/stock/slugs/names are unchanged and the storefront serves the new `<title>`, `description` and `keywords`
in both languages with the canonical and `<h1>` intact.

**To publish:** production admin → **Products → Import products** → upload the file → check the review says *6 updated* →
**Confirm import**. You already have your export as a backup; importing it again restores the previous values.

| SKU | English title | Arabic title |
|---|---|---|
| ARO-AB-001-PNK | Rose Crystal Abaya – Blush Pink Crystal Abaya \| Aroma | عباية روز كريستال وردية بتفاصيل كريستال \| أروما |
| ARO-AB-002-NVY | Midnight Blue Abaya with Matching Sheila \| Aroma | عباية نايت بلو كحلية مع طرحة متناسقة \| أروما |
| ARO-AB-003-BBL | Blue Blossom Abaya – Baby Blue Floral Lace Abaya \| Aroma | عباية بلو بلوسوم سماوية بنقشة زهرية ودانتيل \| أروما |
| ARO-AB-004-SKY | Sky Elegance Abaya – Sky Blue Everyday Abaya \| Aroma | عباية سكاي إيلغنس سماوية للإطلالة اليومية \| أروما |
| ARO-AB-005-IVR | Ivory Flow Abaya – Off-White Abaya with Beige Sheila \| Aroma | عباية آيفوري فلو أوف وايت مع طرحة بيج \| أروما |
| ARO-AB-006-BRG | Burgundy Lace Abaya – Luxury Evening Abaya \| Aroma | عباية بورغندي ليس خمرية بدانتيل فاخر \| أروما |

**How the copy is built** (reuse this pattern for every future product)
- **Title:** `{product name} – {colour + type + key feature} | Aroma` (≤ 60 characters; Arabic ≤ 52). The brand goes last, the searchable
  phrase first; every title is different, so no two pages compete for the same snippet.
- **Description:** ≤ 155 characters, opens with a shopper action ("Shop…" / "تسوقي…"), states colour, key detail and occasion, and closes
  with a claim the site already makes (*Fast delivery across the Kingdom / توصيل سريع في جميع أنحاء المملكة*).
- **Prices are deliberately not in the meta text.** Sale prices change; a stale price in a Google snippet is worse than none.
- **Keywords:** English + Arabic colour/feature phrases, including spelling variants people really type (بورغندي / بورجندي, كحلي / نايت بلو).
  Note **Google ignores the meta keywords tag** for ranking; it is filled because the field exists and costs nothing, not because it helps.
- **Slugs are unchanged on purpose.** The app has no redirect for renamed slugs, so changing one would 404 any existing link or indexed page.

---

## 3. Keyword approach

Group by shopper intent, then give each cluster exactly one page to rank:

| Cluster | Example phrases (verify in Search Console) | Owner page |
|---|---|---|
| Head term | عبايات / عباية، abayas online Saudi Arabia | **Category page** |
| Colour | عباية سماوية، كحلية، وردية، بيضاء / أوف وايت، خمرية | Each product; later a colour collection page |
| Feature | عباية دانتيل، كريستال، بطرحة، أكمام واسعة | Each product |
| Occasion | عباية للمناسبات، سهرة، يومية، للعيد / رمضان | Collection pages + guides |
| Brand | أروما، Aroma abaya, Aroma Gift Center | Home page |
| Informational | كيف أختار مقاس العباية، العناية بالعباية | The existing **Guides** pages (sizing / fit / care) |

Arabic is the primary search language for this market and `/` redirects to `/ar`, so treat the **Arabic title and description as the
main ones**; English is the secondary audience.

---

## 4. Priorities

**P1 — this week (highest impact, least effort)**
1. Import the SEO workbook (§2).
2. **Category page:** in admin → Categories → the ABAYA category: rename to **"Abayas" / "عبايات"** and fill its Meta Title and Meta Description
   (e.g. *Luxury Abayas for Women Online in Saudi Arabia | Aroma* / *عبايات نسائية فاخرة | تسوقي عبايات أونلاين | أروما*) plus a short intro paragraph
   in its description. (Products export doesn't cover categories, so this is a manual edit.)
3. **Assign the brand** ("Aroma") to all six products so it appears in the Product schema. (Create the brand first if it doesn't exist.)
4. **Search Console:** add the domain, submit `https://aromagiftcenter.com/sitemap.xml`, then *URL inspection → Request indexing* for the home page,
   the category page and each product. Record today's numbers as your baseline.
5. Give the homepage its own meta title/description — it currently reuses the hero headline and subtitle, and there is no admin field for it, so this is a small edit to `resources/views/home/index.blade.php` (a development task).

**P2 — next 2–4 weeks**
- **Complete the product copy:** add fabric/material, abaya length and available sizes, and care to each description (both languages). This is
  the content shoppers and Google both look for and it differentiates you from thin listings.
- **Images:** 3–5 per product (front, back, sleeve/detail, styled), WebP, descriptive file names (`rose-crystal-abaya-blush-pink.webp`), and a
  descriptive **alt text per image**. The storefront already supports a per-image alt (it falls back to the product name), but the admin
  product form has no field for it yet — a small development task worth doing before the image work.
- **Internal links:** from each product to the Size guide, Care guide and Returns page; from the category page to the same guides and to the best sellers.
- **Merchandising:** feature your 2–3 best products, use *Sort Order* (1, 2, 3…) to put them first, and keep "New arrival" only for genuinely new pieces.
- **Reviews:** enable/collect real reviews — the schema emits `aggregateRating` only when real reviews exist (which is correct; never fake them).

**P3 — 1–3 months**
- Colour and occasion collection pages (e.g. blue abayas, evening abayas) with 100–150 words of unique intro copy each.
- 1–2 guides a month aimed at questions, not keywords: how to choose an abaya size, matching a sheila, abaya fabrics, caring for lace.
- **Ramadan / Eid 2027 (early in the year):** publish the seasonal landing page 6–8 weeks ahead so it is indexed before demand peaks.
- Product schema extras once the basics are done: several images, shipping and return-policy details.

---

## 5. Technical notes from the codebase

- **Already good — leave alone:** canonical URLs, `hreflang` (ar/en + x-default), `sitemap.xml` (active products and categories, both languages),
  generated `robots.txt` with the sitemap line, Organization/WebSite/Product/Breadcrumb JSON-LD, favicon (fixed to Google's spec earlier today).
- **`/` redirects (302) to the visitor's language, default Arabic.** Fine for search; no change needed.
- **Titles now come from the Meta Title fields** on product and category pages (added with the import/export feature); with the field empty the old `name — brand` title still applies.
- **Validate after publishing:** run one product URL through Google's *Rich Results Test* and *URL Inspection*, and check the Arabic and English versions each show the new title.
- **Page speed:** product images are PNG/WebP straight from upload; converting to properly sized WebP is the cheapest speed win (mobile Core Web Vitals matter for ranking).

---

## 6. Measuring

| When | Do |
|---|---|
| Day 0 | Baseline in Search Console: impressions, clicks, average position, top queries, per page |
| Week 2 | Confirm all 8 pages are indexed with the new titles (URL Inspection → *View crawled page*) |
| Weeks 4–8 | First real signal: which queries each product now appears for; compare CTR on pages whose titles changed. Adjust **one** variable per page at a time so you can tell what worked |
| Monthly | Replace the keyword guesses in §3 with the queries Search Console actually shows; expand copy where you rank on page 2 (positions 8–20) — that is the cheapest gain |

Expect the new titles to be picked up within days but ranking changes to take weeks; with a 6-product catalogue the biggest lever over
the next quarter is content (P2/P3), not tweaking meta text.
