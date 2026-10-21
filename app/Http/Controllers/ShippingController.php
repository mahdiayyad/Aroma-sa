<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\AramexFulfilmentService;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aramex entry points: live delivery quotes for checkout (storefront) and
 * creating a shipment / downloading its label (admin; routes sit in the
 * admin middleware group).
 */
class ShippingController extends Controller
{
    /** @var AramexFulfilmentService */
    private $fulfilment;

    public function __construct(AramexFulfilmentService $fulfilment)
    {
        $this->fulfilment = $fulfilment;
    }

    /**
     * Live Aramex rate for the current cart to the given destination. The
     * weight comes from the server-side cart, never from the client.
     */
    public function getLiveQuote(Request $request, CartService $cart): JsonResponse
    {
        $data = $request->validate([
            'city' => ['required', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'line1' => ['nullable', 'string', 'max:255'],
            'post_code' => ['nullable', 'string', 'max:10'],
        ]);

        $rows = $cart->rows();
        if ($rows === []) {
            return response()->json(['success' => false, 'message' => __('checkout.shipping_quote.empty_cart')], 422);
        }

        if (! $this->fulfilment->isConfigured()) {
            return response()->json(['success' => false, 'message' => __('checkout.shipping_quote.unavailable')], 503);
        }

        $package = $this->fulfilment->cartWeight($rows);
        $result = $this->fulfilment->quote([
            'line1' => $data['line1'] ?? '',
            'city' => $data['city'],
            'post_code' => $data['post_code'] ?? '',
            'country_code' => strtoupper($data['country_code'] ?? 'SA'),
        ], $package['weight_kg'], $package['pieces']);

        if (! $result['success']) {
            // Aramex's own error text stays in the log; shoppers get a plain message.
            return response()->json(['success' => false, 'message' => __('checkout.shipping_quote.failed')], 422);
        }

        return response()->json([
            'success' => true,
            'amount' => $result['amount'],
            'currency' => $result['currency'],
            'weight_kg' => $package['weight_kg'],
            'pieces' => $package['pieces'],
        ]);
    }

    /**
     * Admin: create the Aramex shipment (if not already created) and move the
     * order to "shipped". Redirects back to the order page, which then starts
     * the label download itself, so the page reflects the new AWB and status.
     */
    public function shipOrder(Order $order): RedirectResponse
    {
        $result = $this->fulfilment->ship($order);
        $back = redirect()->route('admin.orders.show', $order);

        if (! $result['success']) {
            return $back->with('error', $result['message']);
        }

        $back->with('status', $result['message']);

        if ($result['warnings'] !== []) {
            $back->with('error', implode(' ', $result['warnings']));
        }

        $shipment = $result['shipment'];
        if ($shipment && $shipment->label_path) {
            $back->with('aramex_label_ready', true);
        }

        return $back;
    }

    /** Admin: download a shipment's label, fetching it from Aramex again if needed. */
    public function downloadLabel(Order $order): Response
    {
        $shipment = $order->aramexShipment;

        if (! $shipment || ! $shipment->hasAwb()) {
            return $this->backWithError($order, __('admin.aramex.no_shipment'));
        }

        if (! $this->fulfilment->ensureLabel($shipment)) {
            return $this->backWithError($order, __('admin.aramex.label_failed'));
        }

        return $this->labelResponse((string) $shipment->label_path, (string) $shipment->awb_number);
    }

    private function labelResponse(string $path, string $awb): Response
    {
        return $this->fulfilment->labelDisk()->download($path, 'aramex-label-'.$awb.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function backWithError(Order $order, string $message): RedirectResponse
    {
        return redirect()->route('admin.orders.show', $order)->with('error', $message);
    }
}
