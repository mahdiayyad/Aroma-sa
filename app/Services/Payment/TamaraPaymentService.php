<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tamara — "Buy Now, Pay Later" (pay in instalments / pay later). Uses the
 * Tamara Checkout API: create a checkout session, redirect the buyer to the
 * returned checkout_url, then verify the order on return.
 *
 * Docs: https://docs.tamara.co/  (Checkout Session)
 */
class TamaraPaymentService implements PaymentGateway
{
    private string $apiToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiToken = (string) config('services.tamara.api_token', '');
        $this->baseUrl  = rtrim((string) config('services.tamara.base_url', 'https://api.tamara.co'), '/');
    }

    public function key(): string
    {
        return 'tamara';
    }

    public function createCheckout(Order $order): array
    {
        if (! $this->apiToken) {
            return ['success' => false, 'error' => 'Tamara is not configured.'];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->post("{$this->baseUrl}/checkout", $this->checkoutPayload($order));

            if (! $response->successful()) {
                Log::error('Tamara checkout failed', [
                    'order'  => $order->order_number,
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return ['success' => false, 'error' => __('checkout.errors.bnpl_unavailable', ['gateway' => 'Tamara'])];
            }

            $data    = $response->json();
            $url     = $data['checkout_url'] ?? null;
            $orderId = $data['order_id'] ?? null;

            if (! $url) {
                return ['success' => false, 'error' => __('checkout.errors.bnpl_unavailable', ['gateway' => 'Tamara'])];
            }

            return ['success' => true, 'redirect_url' => $url, 'reference' => (string) $orderId];
        } catch (Exception $e) {
            Log::error('Tamara error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchStatus(string $reference): ?array
    {
        if (! $this->apiToken) {
            return null;
        }

        try {
            $response = Http::withToken($this->apiToken)->acceptJson()
                ->get("{$this->baseUrl}/orders/{$reference}");

            if (! $response->successful()) {
                return null;
            }

            $data   = $response->json();
            $status = strtolower((string) ($data['status'] ?? ''));

            return [
                'paid'   => in_array($status, ['approved', 'authorised', 'fully_captured', 'partially_captured'], true),
                'status' => $status,
                'raw'    => $data,
            ];
        } catch (Exception $e) {
            Log::error('Tamara status error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string,mixed> */
    private function checkoutPayload(Order $order): array
    {
        [$firstName, $lastName] = $this->splitName($order->customer_name);
        $shipping = is_array($order->shipping_address) ? $order->shipping_address : [];
        $callback = route('payment.callback');

        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'reference_id' => (string) $item->id,
                'type'         => 'Physical',
                'name'         => $this->itemName($item->product_data),
                'sku'          => (string) ($item->product_data['sku'] ?? $item->product_id),
                'quantity'     => (int) $item->quantity,
                'unit_price'   => $this->money($item->unit_price),
                'total_amount' => $this->money($item->line_total),
            ];
        }

        return [
            'order_reference_id' => $order->order_number,
            'total_amount'       => $this->money($order->total_amount),
            'description'        => 'Order '.$order->order_number,
            'country_code'       => 'SA',
            'payment_type'       => 'PAY_BY_INSTALMENTS',
            'locale'             => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'items'              => $items,
            'consumer'           => [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'phone_number' => $order->customer_phone,
                'email'        => $order->customer_email,
            ],
            'shipping_address' => [
                'first_name'   => $firstName,
                'last_name'    => $lastName,
                'line1'        => $shipping['street_address'] ?? '',
                'city'         => $shipping['city'] ?? '',
                'country_code' => 'SA',
                'phone_number' => $order->customer_phone,
            ],
            'merchant_url' => [
                'success'      => $callback,
                'failure'      => $callback,
                'cancel'       => $callback,
                'notification' => route('payment.webhook'),
            ],
        ];
    }

    /** @return array{0:string,1:string} */
    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $first = $parts[0] ?? 'Customer';
        $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first, $last];
    }

    /** @param array<string,mixed>|null $productData */
    private function itemName($productData): string
    {
        $name = is_array($productData) ? ($productData['name'] ?? null) : null;

        return is_array($name) ? (reset($name) ?: 'Item') : (string) ($name ?: 'Item');
    }

    /** @return array{amount:string,currency:string} */
    private function money($value): array
    {
        return ['amount' => number_format((float) $value, 2, '.', ''), 'currency' => 'SAR'];
    }
}
