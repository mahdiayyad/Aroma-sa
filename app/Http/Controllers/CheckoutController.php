<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\AddressRequest;
use App\Http\Requests\Checkout\GiftOptionsRequest;
use App\Http\Requests\Checkout\PaymentRequest;
use App\Http\Requests\Checkout\PromoCodeRequest;
use App\Models\Address;
use App\Models\GiftCard;
use App\Models\Order;
use App\Services\AddressResolver;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private CartService $cart;
    private CheckoutService $checkout;
    private PaymentGatewayManager $gateways;
    private AddressResolver $addressResolver;
    private PromoCodeService $promoCodes;

    public function __construct(
        CartService $cart,
        CheckoutService $checkout,
        PaymentGatewayManager $gateways,
        AddressResolver $addressResolver,
        PromoCodeService $promoCodes
    ) {
        $this->cart = $cart;
        $this->checkout = $checkout;
        $this->gateways = $gateways;
        $this->addressResolver = $addressResolver;
        $this->promoCodes = $promoCodes;
    }

    /**
     * Redirect target if a required earlier checkout step hasn't actually
     * been completed in this session yet — null when everything needed is
     * present. Every step already checked checkout.billing_address this
     * way; gift had no equivalent gate, which meant a shopper could jump
     * straight from the address step to payment. storeGiftOptions always
     * writes checkout.gift regardless of the choice made on that step (e.g.
     * "not a gift" still sets it, just with is_gift=false), so
     * session()->has(...) is a reliable "was this step ever completed"
     * check, not a truthiness check on the step's answer.
     */
    private function missingStepRedirect(bool $requireGift = false): ?RedirectResponse
    {
        if (!session()->has('checkout.billing_address')) {
            return redirect()->route('checkout.address');
        }
        if ($requireGift && !session()->has('checkout.gift')) {
            return redirect()->route('checkout.gift-options');
        }

        return null;
    }

    /**
     * $request->validated() returns 'numeric' fields as whatever raw type
     * they arrived as (a string, from HTML form submission) — Laravel's
     * 'numeric' rule validates but never casts. AddressResolver::resolve()
     * takes a strict ?float, so every coordinate must pass through this first.
     */
    private function toFloatOrNull($value): ?float
    {
        return $value !== null && $value !== '' ? (float) $value : null;
    }

    /**
     * Show cart review page
     *
     * @return View|RedirectResponse
     */
    public function review()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        $rows = $this->cart->rows();
        $totals = $this->checkout->calculateTotals();

        return view('checkout.review', [
            'items' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * Show the "sign in / register / continue as guest" choice screen.
     * Authenticated users skip straight to the address form.
     *
     * @return View|RedirectResponse
     */
    public function start()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        if (auth()->check()) {
            return redirect()->route('checkout.address');
        }

        return view('checkout.auth-choice');
    }

    /**
     * Send guest to login, returning to checkout address afterwards.
     */
    public function redirectToLogin(): RedirectResponse
    {
        session(['url.intended' => route('checkout.address')]);

        return redirect()->route('login');
    }

    /**
     * Send guest to registration, returning to checkout address afterwards.
     */
    public function redirectToRegister(): RedirectResponse
    {
        session(['url.intended' => route('checkout.address')]);

        return redirect()->route('register');
    }

    /**
     * Show address form
     *
     * @return View|RedirectResponse
     */
    public function showAddressForm()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        $user = auth()->user();
        $addresses = $user ? $user->addresses()->get() : collect();

        // Authenticated shoppers skip auth-choice entirely (see start() above),
        // so their predecessor step is review; a guest actually passed through
        // auth-choice, so that's their true predecessor — hardcoding this to
        // checkout.review always would silently skip auth-choice for guests.
        $backRoute = $user ? route('checkout.review') : route('checkout.start');

        return view('checkout.address', [
            'addresses' => $addresses,
            'user' => $user,
            'backRoute' => $backRoute,
        ]);
    }

    /**
     * Save addresses and proceed to payment
     */
    public function storeAddress(AddressRequest $request): RedirectResponse
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        $data = $request->validated();

        $billingAddress = $this->addressResolver->resolve([
            'recipient_name' => $data['billing_address']['recipient_name'],
            'phone'          => $data['billing_address']['phone'],
            'email'          => $data['billing_address']['email'] ?? null,
        ], $data['billing_address']['method'] ?? null, $data['billing_address']['location_code'] ?? null, $this->toFloatOrNull($data['billing_address']['latitude'] ?? null), $this->toFloatOrNull($data['billing_address']['longitude'] ?? null), $data['billing_address']);

        if ($billingAddress === null) {
            return back()
                ->withErrors(['billing_address.location_code' => __('location.errors.not_found')])
                ->withInput();
        }

        // The address form collects a single address; a distinct shipping
        // address is optional. Fall back to the billing address whenever the
        // customer hasn't entered a separate one (this also avoids the
        // "Undefined index: shipping_address" when the field isn't submitted).
        $sameAsBilling = (bool) ($data['use_shipping_for_billing'] ?? true);
        $shippingHasCode = ! empty($data['shipping_address']['location_code']);
        $shippingHasCoordinates = ! empty($data['shipping_address']['latitude']) && ! empty($data['shipping_address']['longitude']);
        $shippingIsManual = ($data['shipping_address']['method'] ?? null) === Address::METHOD_MANUAL;

        if (! $sameAsBilling && ($shippingHasCode || $shippingHasCoordinates || $shippingIsManual)) {
            $shippingAddress = $this->addressResolver->resolve([
                'recipient_name' => $data['shipping_address']['recipient_name'],
                'phone'          => $data['shipping_address']['phone'],
            ], $data['shipping_address']['method'] ?? null, $data['shipping_address']['location_code'] ?? null, $this->toFloatOrNull($data['shipping_address']['latitude'] ?? null), $this->toFloatOrNull($data['shipping_address']['longitude'] ?? null), $data['shipping_address']);

            if ($shippingAddress === null) {
                return back()
                    ->withErrors(['shipping_address.location_code' => __('location.errors.not_found')])
                    ->withInput();
            }
        } else {
            $shippingAddress = $billingAddress;
        }

        // Store in session for next step
        session([
            'checkout.billing_address' => $billingAddress,
            'checkout.shipping_address' => $shippingAddress,
            'checkout.customer_notes' => $data['customer_notes'] ?? null,
        ]);

        // TODO: Save address to user's address book if authenticated

        return redirect()->route('checkout.gift-options');
    }

    /**
     * Show the Gift Options step: "is this a gift?", recipient details (when
     * it is), gift wrap, greeting card + message studio, anonymous sender.
     *
     * @return View|RedirectResponse
     */
    public function showGiftOptions()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        if ($redirect = $this->missingStepRedirect()) {
            return $redirect;
        }

        $gift = session('checkout.gift', []);

        return view('checkout.gift-options', [
            'giftCards' => GiftCard::active()->orderBy('sort_order')->get(),
            'gift'      => $gift,
            // The recipient fields live in checkout.shipping_address (see the
            // "Recipient model" decision) — only meaningful to repopulate when
            // the previous visit here actually set is_gift.
            'recipient' => ($gift['is_gift'] ?? false) ? session('checkout.shipping_address', []) : [],
            'wrapFee'   => (float) config('aroma.gifting.wrap_fee', 0),
            'maxChars'  => (int) config('aroma.gifting.message_max_chars', 200),
            'maxLines'  => (int) config('aroma.gifting.message_max_lines', 5),
            'suggestionCategories' => __('gift.suggestion_categories'),
        ]);
    }

    /**
     * Save the Gift Options step and proceed to Order Review.
     */
    public function storeGiftOptions(GiftOptionsRequest $request): RedirectResponse
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        $data = $request->validated();
        $isGift = (bool) ($data['is_gift'] ?? false);
        $isAnonymous = $isGift && (bool) ($data['is_anonymous'] ?? false);

        if ($isGift) {
            $recipientAddress = $this->addressResolver->resolve([
                'recipient_name' => $data['recipient']['recipient_name'],
                'phone'          => $data['recipient']['phone'],
            ], $data['recipient']['method'] ?? null, $data['recipient']['location_code'] ?? null, $this->toFloatOrNull($data['recipient']['latitude'] ?? null), $this->toFloatOrNull($data['recipient']['longitude'] ?? null), $data['recipient']);

            if ($recipientAddress === null) {
                return back()
                    ->withErrors(['recipient.location_code' => __('location.errors.not_found')])
                    ->withInput();
            }

            // The recipient's own address IS checkout.shipping_address — see
            // the "Recipient model" decision in the checkout refactor analysis.
            session(['checkout.shipping_address' => $recipientAddress]);
        }

        // An anonymous sender never has their name or signature attached,
        // regardless of what was submitted — enforced here, not just in the UI.
        // A resubmit (e.g. after a validation error elsewhere on this page)
        // won't carry new signature data if the shopper didn't redraw it, so
        // fall back to whatever was already saved in the session rather than
        // silently discarding it.
        $signaturePath = null;
        if ($isGift && !$isAnonymous) {
            if (!empty($data['gift_signature_data'])) {
                $signaturePath = $this->storeSignature($data['gift_signature_data']);
            } else {
                $signaturePath = session('checkout.gift.signature');
            }
        }

        $cardId = $isGift ? ($data['greeting_card_id'] ?? null) : null;
        // Snapshot the price at selection time, not at order time — matches
        // wrap_fee's own reasoning below (see the migration comment on
        // orders.greeting_card_fee for why this shouldn't re-read live).
        $cardFee = 0;
        if ($cardId) {
            $selectedCard = GiftCard::find($cardId);
            $cardFee = $selectedCard ? (float) $selectedCard->price : 0;
        }

        session(['checkout.gift' => [
            'is_gift'      => $isGift,
            'message'      => $isGift ? ($data['gift_message'] ?? null) : null,
            'is_anonymous' => $isAnonymous,
            'wrap_fee'     => $isGift && ($data['gift_wrap'] ?? false) ? (float) config('aroma.gifting.wrap_fee', 0) : 0,
            'card_id'      => $cardId,
            'card_fee'     => $cardFee,
            'to'           => $isGift ? ($data['gift_to'] ?? null) : null,
            'from'         => $isGift && !$isAnonymous ? ($data['gift_from'] ?? null) : null,
            'signature'    => $signaturePath,
            'media_url'    => $isGift ? ($data['gift_media_url'] ?? null) : null,
        ]]);

        return redirect()->route('checkout.order-review');
    }

    /** Decode the signature pad's base64 PNG and store it on the public disk. */
    private function storeSignature(string $dataUri): string
    {
        $encoded = substr($dataUri, strpos($dataUri, ',') + 1);
        $path = 'gift-signatures/'.Str::random(32).'.png';

        Storage::disk('public')->put($path, base64_decode($encoded));

        return $path;
    }

    /**
     * Show the consolidated Order Review: items, recipient, gift summary,
     * and totals, each with an Edit link back to its step.
     *
     * @return View|RedirectResponse
     */
    public function showOrderReview()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        if ($redirect = $this->missingStepRedirect(true)) {
            return $redirect;
        }

        $gift = session('checkout.gift', []);

        return view('checkout.order-review', [
            'totals'   => $this->totalsWithGiftExtras(),
            'shipping' => session('checkout.shipping_address', []),
            'gift'     => $gift,
            'giftCard' => !empty($gift['card_id']) ? GiftCard::find($gift['card_id']) : null,
            'promo'    => session('checkout.promo'),
        ]);
    }

    /**
     * Apply a promo code at the Order Review step. Non-stackable: if one is
     * already applied, the customer must remove it first — enforced here,
     * not just hidden in the UI, so a replayed/manipulated request can't
     * silently combine two discounts.
     */
    public function applyPromo(PromoCodeRequest $request): RedirectResponse
    {
        if (session()->has('checkout.promo')) {
            return back()->with('error', __('promo.errors.already_applied'));
        }

        $result = $this->promoCodes->validate($request->validated()['code'], auth()->user());

        if (! $result['valid']) {
            return back()->withErrors(['code' => $result['error']])->withInput();
        }

        session(['checkout.promo' => [
            'code' => $result['promo_code']->code,
            'promo_code_id' => $result['promo_code']->id,
            'discount_amount' => $result['discount_amount'],
            'free_shipping' => $result['free_shipping'],
        ]]);

        return back()->with('status', __('promo.applied'));
    }

    public function removePromo(): RedirectResponse
    {
        session()->forget('checkout.promo');

        return back()->with('status', __('promo.removed'));
    }

    /**
     * calculateTotals() is cart-only math; the gift wrap fee and the chosen
     * greeting card's fee both live in the checkout session (set in
     * storeGiftOptions()), so they're layered on here for display — the
     * same way createOrder() layers them on when charging. Keeps the Order
     * Review / Payment totals from ever understating what the customer is
     * actually about to pay.
     */
    private function totalsWithGiftExtras(): array
    {
        $totals = $this->checkout->calculateTotals();
        $totals['gift_wrap_fee'] = (float) session('checkout.gift.wrap_fee', 0);
        $totals['greeting_card_fee'] = (float) session('checkout.gift.card_fee', 0);
        $totals['total_amount'] += $totals['gift_wrap_fee'] + $totals['greeting_card_fee'];

        return $totals;
    }

    /**
     * Show payment method selection
     *
     * @return View|RedirectResponse
     */
    public function showPaymentForm()
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        if ($redirect = $this->missingStepRedirect(true)) {
            return $redirect;
        }

        $totals = $this->totalsWithGiftExtras();
        $user = auth()->user();

        return view('checkout.payment', [
            'totals' => $totals,
            'gateways' => config('aroma.payments.methods', []),
            'promo' => session('checkout.promo'),
            // Most-recent explicit choice wins: whatever the shopper typed/
            // kept at the address step (guest-required, auth-optional there)
            // over the account email, since they may have deliberately typed
            // something different (e.g. gifting to another inbox). Only
            // falls back to the account email when the session has none —
            // the phone-only OTP-account case this field exists to cover.
            'emailPrefill' => session('checkout.billing_address.email') ?: optional($user)->email,
        ]);
    }

    /**
     * Process payment - create order and redirect to Moyasar
     */
    public function storePayment(PaymentRequest $request): RedirectResponse
    {
        $validation = $this->checkout->validateCart();

        if (!$validation['valid']) {
            return redirect()->route('cart.index')->with('error', $validation['error']);
        }

        if ($redirect = $this->missingStepRedirect(true)) {
            return $redirect;
        }

        $data = $request->validated();
        $user = auth()->user();
        $billing = session('checkout.billing_address', []);
        $gatewayKey = $data['gateway']; // moyasar | tabby | tamara

        try {
            $order = DB::transaction(function () use ($data, $user, $billing, $gatewayKey) {
                $order = $this->checkout->createOrder($user, [
                    'billing_address'  => $billing,
                    'shipping_address' => session('checkout.shipping_address'),
                    'customer_name'    => $user ? $user->name : ($billing['recipient_name'] ?? 'Guest'),
                    // Always the payment step's own validated email — never
                    // silently falls back to a possibly-null $user->email
                    // (users.email is nullable for phone-first OTP accounts)
                    // or a placeholder string. PaymentRequest guarantees this
                    // is always present and a real address before an order
                    // (against a NOT NULL customer_email column) ever exists.
                    'customer_email'   => $data['email'],
                    'customer_phone'   => $billing['phone'] ?? '',
                    'customer_notes'   => session('checkout.customer_notes'),

                    'is_gift'               => session('checkout.gift.is_gift', false),
                    'gift_message'          => session('checkout.gift.message'),
                    'is_anonymous'          => session('checkout.gift.is_anonymous', false),
                    'gift_wrap_fee'         => session('checkout.gift.wrap_fee', 0),
                    'greeting_card_id'      => session('checkout.gift.card_id'),
                    'greeting_card_fee'     => session('checkout.gift.card_fee', 0),
                    'gift_card_to'          => session('checkout.gift.to'),
                    'gift_card_from'        => session('checkout.gift.from'),
                    'gift_signature'        => session('checkout.gift.signature'),
                    'gift_media_url'        => session('checkout.gift.media_url'),
                ]);

                $this->checkout->createPayment($order, $gatewayKey, $data['method']);

                return $order;
            });

            // Route to whichever gateway the customer selected. Deliberately
            // NOT clearing the cart / checkout session, or marking this order
            // "completed" for confirmation-page access, until the gateway
            // actually confirms the checkout below — doing that beforehand
            // (the old behaviour) meant a gateway failure left the customer
            // bounced to an already-empty cart with their real error message
            // overwritten, address/gift session state gone (no coherent way
            // to retry), while the order was simultaneously viewable at its
            // "confirmed" URL despite never being paid.
            $result = $this->gateways->for($gatewayKey)->createCheckout($order);

            if (! ($result['success'] ?? false)) {
                Log::error('Gateway checkout failed', [
                    'gateway'  => $gatewayKey,
                    'order_id' => $order->id,
                    'error'    => $result['error'] ?? null,
                ]);

                // Never flash the raw gateway error to the customer — it can
                // be an internal/config detail (e.g. "API keys not
                // configured"), not something safe to show a shopper. The
                // real message is already logged above for diagnosis. Cart
                // and checkout session are untouched, so the customer lands
                // back on the payment step able to actually retry.
                return redirect()->route('checkout.payment')->with(
                    'error',
                    __('checkout.errors.payment_failed')
                );
            }

            // Gateway accepted the checkout — now it's safe to consume the
            // cart/checkout-session state and let this session view the
            // order's confirmation page once the customer returns.
            $this->cart->clear();
            session()->forget([
                'checkout.billing_address', 'checkout.shipping_address', 'checkout.customer_notes',
                'checkout.gift', 'checkout.promo',
            ]);
            session()->push('checkout.completed_orders', $order->id);

            // Keep what the callback needs to verify + finalise this order.
            session([
                'checkout.gateway'   => $gatewayKey,
                'checkout.reference' => $result['reference'] ?? null,
                'checkout.order_id'  => $order->id,
            ]);

            return redirect()->away($result['redirect_url']);
        } catch (\Exception $e) {
            Log::error('Checkout error', ['error' => $e->getMessage()]);

            return redirect()->route('checkout.payment')->with(
                'error',
                __('checkout.errors.payment_failed')
            );
        }
    }

    /**
     * Handle the return from any gateway (Moyasar / Tabby / Tamara).
     * Verifies the payment against the selected gateway and finalises the order.
     */
    public function paymentCallback(): RedirectResponse
    {
        $orderId    = session('checkout.order_id');
        $gatewayKey = session('checkout.gateway');
        $reference  = session('checkout.reference');

        $order = $orderId ? Order::find($orderId) : null;

        if (! $order || ! $gatewayKey || ! $reference) {
            return redirect()->route('cart.index')->with('error', __('checkout.errors.payment_failed'));
        }

        // The gateway is the source of truth on return (webhooks can't reach a
        // local/test host), so verify with it directly and finalise if paid.
        $status = $this->gateways->for($gatewayKey)->fetchStatus($reference);

        session()->forget(['checkout.gateway', 'checkout.reference', 'checkout.order_id']);

        $gatewayPaid = $status && ($status['paid'] ?? false);

        if ($gatewayPaid && ! $order->isPaid()) {
            $payment = $order->payment()->latest('id')->first();

            if ($payment) {
                $this->checkout->markOrderAsPaid($order, $payment);
            } else {
                $order->update(['status' => Order::STATUS_PAID]);
            }
        }

        if ($gatewayPaid || $order->isPaid()) {
            return redirect()->route('home', app()->getLocale())->with(
                'status',
                __('checkout.success.order_created', ['order_number' => $order->order_number])
            );
        }

        return redirect()->route('checkout.payment')->with(
            'error',
            __('checkout.errors.payment_failed')
        );
    }

    /**
     * Show order confirmation
     */
    public function confirmation(Order $order): View
    {
        $user = auth()->user();
        $ownedByUser = $user && (int) $order->user_id === (int) $user->id;

        // Guests have no account to authorise against, so a freshly-placed
        // order is remembered in the session (see storePayment). Without this
        // guard any visitor could read another customer's order — and its PII —
        // just by guessing the sequential id.
        $completedInSession = in_array($order->id, session('checkout.completed_orders', []), true);

        abort_unless($ownedByUser || $completedInSession, 403);

        return view('checkout.confirmation', [
            'order' => $order->load('items'),
        ]);
    }
}
