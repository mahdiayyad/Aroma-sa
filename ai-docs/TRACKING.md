# E-commerce tracking (Meta Pixel, TikTok Pixel, GTM)

## Who sends what

| Destination | Sent by | Notes |
|---|---|---|
| Meta Pixel | The site (`public/js/aroma-tracking.js`) | Base code + PageView in `layouts/partials/marketing-pixels.blade.php` |
| TikTok Pixel | The site (`public/js/aroma-tracking.js`) | Base code + page view in the same partial |
| GTM `dataLayer` | The site (`public/js/aroma-tracking.js`) | GA4 e-commerce format; the container loads only when `GTM_CONTAINER_ID` is set |

**Do not add Meta or TikTok tags for these events in GTM.** The site already sends them; a GTM tag would count each one twice. Use GTM for GA4 or other tools, triggered by the dataLayer events below.

Everything loads only when `APP_ENV=production`, and never for signed-in staff/admins or in the admin panel.

## Events

Built on the server by `App\Services\TrackingService` (one shape for all three destinations), mapped per platform in `aroma-tracking.js`.

| Customer action | When it fires | dataLayer | Meta | TikTok |
|---|---|---|---|---|
| View product | Product page load | `view_item` | `ViewContent` | `ViewContent` |
| View category | Category page load (products on that page) | `view_item_list` | `ViewCategory` (custom) | `ViewCategory` (custom) |
| Add to cart | Server confirmed the add (product page, cards, cart-modal suggestions, cart quantity increase) | `add_to_cart` | `AddToCart` | `AddToCart` |
| Remove from cart | Server confirmed a removal or quantity decrease | `remove_from_cart` | `RemoveFromCart` (custom) | `RemoveFromCart` (custom) |
| View cart | Cart page load with items | `view_cart` | `ViewCart` (custom) | `ViewCart` (custom) |
| Initiate checkout | First checkout page, once per cart (reload/back/next step don't repeat it; a changed cart does) | `begin_checkout` | `InitiateCheckout` | `InitiateCheckout` |
| Add payment info | Payment form actually submitted (after browser validation) | `add_payment_info` | `AddPaymentInfo` | `AddPaymentInfo` |
| Purchase | First page after the gateway confirms payment | `purchase` | `Purchase` | `CompletePayment` |
| Add to wishlist | Server confirmed the add | `add_to_wishlist` | `AddToWishlist` | `AddToWishlist` |

Meta has no standard ViewCart, ViewCategory or RemoveFromCart event, so those are sent with `fbq('trackCustom', …)`. The store has no product search, so no Search event is sent.

## Parameters

- **Product id**: the product SKU everywhere (`item_id` / Meta `content_ids`, `contents[].id` / TikTok `contents[].content_id`). A Meta or TikTok catalogue must use SKUs as its ids.
- **Names**: English product and category names, whatever the shopping language.
- **Currency**: `SAR`.
- **value**:
  - product, cart and checkout events: item price × quantity;
  - AddPaymentInfo and Purchase: the amount payable (items + shipping + gift extras − discount).
  - Purchase also carries `transaction_id` (order number), `shipping`, `tax`, `discount` and `coupon` in the dataLayer, and `order_id` to Meta.
- **Event id**: every event carries `event_id` (Meta `eventID`, TikTok `event_id`); for Purchase it is `purchase-<order number>`, ready for Conversions API / Events API deduplication.
- No customer personal data (name, email, phone, address) is sent.

## Purchase reliability

- Fires only after the gateway (Moyasar, Tamara, Tabby) confirms the payment in `CheckoutController::paymentCallback()`, or when the shopper returns to an order a webhook already marked paid (`showPaymentForm()`). Clicking Place Order never fires it; failed or cancelled payments never fire it.
- At most once per order: `orders.purchase_tracked_at` is claimed with one conditional UPDATE before the event is queued, so a refreshed page, a replayed gateway return or a second tab can't produce another.
- The browser also remembers sent Purchase event ids (localStorage), so re-running a cached copy of the page doesn't resend it.
- Limitation: a browser-only pixel can't report a purchase whose shopper never comes back to the site (closed tab after paying). Server-side Conversions API / Events API would close that gap; it needs access tokens.

## Automatic "ButtonClick" events

Meta's automatic events (Events Manager → the pixel → Settings) report clicks as `SubscribedButtonClick` ("ButtonClick"). They are separate from the events above and can stay on or be turned off there. If any codeless events were set up with Meta's Event Setup Tool (e.g. a button mapped to AddToCart or Purchase), remove them, or those actions will be counted twice. TikTok's automatic event detection in TikTok Events Manager works the same way.
