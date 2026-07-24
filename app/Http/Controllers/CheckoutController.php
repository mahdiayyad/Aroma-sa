<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\AddressRequest;
use App\Http\Requests\Checkout\PaymentRequest;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private CartService $cart;
    private CheckoutService $checkout;
    private PaymentGatewayManager $gateways;

    public function __construct(
        CartService $cart,
        CheckoutService $checkout,
        PaymentGatewayManager $gateways
    ) {
        $this->cart = $cart;
        $this->checkout = $checkout;
        $this->gateways = $gateways;
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

        return view('checkout.address', [
            'addresses' => $addresses,
            'user' => $user,
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

        $billingAddress = $data['billing_address'];

        // The address form collects a single address; a distinct shipping
        // address is optional. Fall back to the billing address whenever the
        // customer hasn't entered a separate one (this also avoids the
        // "Undefined index: shipping_address" when the field isn't submitted).
        $sameAsBilling = (bool) ($data['use_shipping_for_billing'] ?? true);
        $shippingAddress = (! $sameAsBilling && ! empty($data['shipping_address']))
            ? $data['shipping_address']
            : $billingAddress;

        // Store in session for next step
        session([
            'checkout.billing_address' => $billingAddress,
            'checkout.shipping_address' => $shippingAddress,
            'checkout.customer_notes' => $data['customer_notes'] ?? null,
        ]);

        // TODO: Save address to user's address book if authenticated

        return redirect()->route('checkout.payment');
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

        if (!session()->has('checkout.billing_address')) {
            return redirect()->route('checkout.address');
        }

        $totals = $this->checkout->calculateTotals();

        return view('checkout.payment', [
            'totals' => $totals,
            'gateways' => config('aroma.payments.methods', []),
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

        if (!session()->has('checkout.billing_address')) {
            return redirect()->route('checkout.address');
        }

        $data = $request->validated();
        $user = auth()->user();
        $billing = session('checkout.billing_address', []);
        $gatewayKey = $data['gateway']; // moyasar | tabby | tamara

        try {
            $order = DB::transaction(function () use ($data, $user, $billing, $gatewayKey) {
                $order = $this->checkout->createOrder(
                    $user,
                    $billing,
                    session('checkout.shipping_address'),
                    $user ? $user->name : ($billing['recipient_name'] ?? 'Guest'),
                    $user ? $user->email : ($billing['email'] ?? 'noemail@aroma.sa'),
                    $billing['phone'] ?? '',
                    session('checkout.customer_notes')
                );

                $this->checkout->createPayment($order, $gatewayKey, $data['method']);

                $this->cart->clear();
                session()->forget(['checkout.billing_address', 'checkout.shipping_address', 'checkout.customer_notes']);

                return $order;
            });

            // Remember the order for this session so the customer can view its
            // confirmation after returning from the gateway (without IDOR).
            session()->push('checkout.completed_orders', $order->id);

            // Route to whichever gateway the customer selected.
            $result = $this->gateways->for($gatewayKey)->createCheckout($order);

            if (! ($result['success'] ?? false)) {
                Log::error('Gateway checkout failed', [
                    'gateway'  => $gatewayKey,
                    'order_id' => $order->id,
                    'error'    => $result['error'] ?? null,
                ]);

                return redirect()->route('checkout.payment')->with(
                    'error',
                    $result['error'] ?? __('checkout.errors.payment_failed')
                );
            }

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
