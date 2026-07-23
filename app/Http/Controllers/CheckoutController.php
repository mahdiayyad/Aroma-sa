<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Checkout\AddressRequest;
use App\Http\Requests\Checkout\PaymentRequest;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\Payment\MoyasarPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    private CartService $cart;
    private CheckoutService $checkout;
    private MoyasarPaymentService $paymentService;

    public function __construct(
        CartService $cart,
        CheckoutService $checkout,
        MoyasarPaymentService $paymentService
    ) {
        $this->cart = $cart;
        $this->checkout = $checkout;
        $this->paymentService = $paymentService;
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

        // Prepare shipping address
        $shippingAddress = $data['use_shipping_for_billing'] ?? false
            ? $data['billing_address']
            : $data['shipping_address'];

        // Store in session for next step
        session([
            'checkout.billing_address' => $data['billing_address'],
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

        try {
            $order = DB::transaction(function () use ($data, $user) {
                // Create order
                $order = $this->checkout->createOrder(
                    $user,
                    session('checkout.billing_address'),
                    session('checkout.shipping_address'),
                    $user ? $user->name : ($data['billing_address']['recipient_name'] ?? 'Guest'),
                    $user ? $user->email : ($data['billing_address']['email'] ?? session('checkout.billing_address')['email'] ?? 'noemail@aroma.sa'),
                    session('checkout.billing_address')['phone'] ?? '',
                    session('checkout.customer_notes')
                );

                // Create payment record
                $this->checkout->createPayment(
                    $order,
                    $data['gateway'],
                    $data['method']
                );

                // Clear cart
                $this->cart->clear();

                // Clear checkout session
                session()->forget(['checkout.billing_address', 'checkout.shipping_address', 'checkout.customer_notes']);

                return $order;
            });

            // Create invoice with Moyasar and get payment URL
            $paymentResult = $this->paymentService->createInvoice($order);

            if (!$paymentResult['success']) {
                Log::error('Failed to create Moyasar invoice', ['order_id' => $order->id]);

                return redirect()->route('checkout.payment')->with(
                    'error',
                    __('checkout.errors.payment_failed')
                );
            }

            // Store invoice ID in session for later reference
            session(['checkout.invoice_id' => $paymentResult['invoice_id']]);

            // Redirect to Moyasar payment page
            return redirect()->away($paymentResult['url']);
        } catch (\Exception $e) {
            Log::error('Checkout error', ['error' => $e->getMessage()]);

            return redirect()->route('checkout.payment')->with(
                'error',
                __('checkout.errors.payment_failed')
            );
        }
    }

    /**
     * Handle payment callback from Moyasar
     * Called after customer completes or fails payment
     */
    public function paymentCallback(): RedirectResponse
    {
        $invoiceId = session('checkout.invoice_id');

        if (!$invoiceId) {
            return redirect()->route('cart.index')->with('error', __('checkout.errors.payment_failed'));
        }

        // Get payment status from Moyasar
        $paymentData = $this->paymentService->getPaymentStatus($invoiceId);

        if (!$paymentData) {
            return redirect()->route('cart.index')->with('error', __('checkout.errors.payment_failed'));
        }

        // Find order by invoice reference
        $order = Order::where('order_number', $paymentData['reference_id'] ?? null)->first();

        if (!$order) {
            return redirect()->route('cart.index')->with('error', __('checkout.errors.payment_failed'));
        }

        session()->forget('checkout.invoice_id');

        // Payment status is handled by webhook, so just show confirmation if paid
        if ($order->isPaid()) {
            return redirect()->route('order.confirmation', $order)->with(
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
        if (auth()->check() && auth()->id() !== $order->user_id) {
            abort(403);
        }

        return view('checkout.confirmation', [
            'order' => $order->load('items'),
        ]);
    }
}
