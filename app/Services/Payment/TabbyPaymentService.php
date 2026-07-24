<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tabby — "Buy Now, Pay Later" (split in instalments). Uses the Tabby
 * Checkout API v2: create a checkout session, redirect the buyer to the
 * returned instalments web_url, then verify the payment on return.
 *
 * Docs: https://docs.tabby.ai/  (Checkout API)
 */
class TabbyPaymentService implements PaymentGateway
{
    private string $secretKey;
    private string $publicKey;
    private string $merchantCode;
    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey    = (string) config('services.tabby.secret_key', '');
        $this->publicKey    = (string) config('services.tabby.public_key', '');
        $this->merchantCode = (string) config('services.tabby.merchant_code', '');
        $this->baseUrl      = rtrim((string) config('services.tabby.base_url', 'https://api.tabby.ai'), '/');
    }

    public function key(): string
    {
        return 'tabby';
    }

    public function createCheckout(Order $order): array
    {
        if (! $this->secretKey || ! $this->merchantCode) {
            return ['success' => false, 'error' => 'Tabby is not configured.'];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/v2/checkout", $this->sessionPayload($order));

            if (! $response->successful()) {
                Log::error('Tabby checkout failed', [
                    'order'  => $order->order_number,
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);

                return ['success' => false, 'error' => 'Tabby session could not be created.'];
            }

            $data      = $response->json();
            $status    = $data['status'] ?? '';
            $webUrl    = data_get($data, 'configuration.available_products.installments.0.web_url');
            $paymentId = data_get($data, 'payment.id', $data['id'] ?? null);

            // Tabby pre-scores the buyer; a rejected session has no payable URL.
            if ($status === 'rejected' || ! $webUrl) {
                return ['success' => false, 'error' => __('checkout.errors.bnpl_unavailable', ['gateway' => 'Tabby'])];
            }

            return ['success' => true, 'redirect_url' => $webUrl, 'reference' => (string) $paymentId];
        } catch (Exception $e) {
            Log::error('Tabby error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchStatus(string $reference): ?array
    {
        if (! $this->secretKey) {
            return null;
        }

        try {
            $response = Http::withToken($this->secretKey)->acceptJson()
                ->get("{$this->baseUrl}/api/v2/payments/{$reference}");

            if (! $response->successful()) {
                return null;
            }

            $data   = $response->json();
            $status = strtoupper((string) ($data['status'] ?? ''));

            return [
                'paid'   => in_array($status, ['AUTHORIZED', 'CLOSED'], true),
                'status' => strtolower($status),
                'raw'    => $data,
            ];
        } catch (Exception $e) {
            Log::error('Tabby status error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string,mixed> */
    private function sessionPayload(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'title'        => $this->itemName($item->product_data),
                'quantity'     => (int) $item->quantity,
                'unit_price'   => $this->amount($item->unit_price),
                'reference_id' => (string) $item->product_id,
                'category'     => 'general',
            ];
        }

        $callback = route('payment.callback');

        return [
            'payment' => [
                'amount'      => $this->amount($order->total_amount),
                'currency'    => 'SAR',
                'description' => 'Order '.$order->order_number,
                'buyer'       => [
                    'phone' => $order->customer_phone,
                    'email' => $order->customer_email,
                    'name'  => $order->customer_name,
                ],
                'order' => [
                    'reference_id' => $order->order_number,
                    'items'        => $items,
                ],
            ],
            'lang'          => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'merchant_code' => $this->merchantCode,
            'merchant_urls' => [
                'success' => $callback,
                'cancel'  => $callback,
                'failure' => $callback,
            ],
        ];
    }

    /** @param array<string,mixed>|null $productData */
    private function itemName($productData): string
    {
        $name = is_array($productData) ? ($productData['name'] ?? null) : null;

        return is_array($name) ? (reset($name) ?: 'Item') : (string) ($name ?: 'Item');
    }

    private function amount($value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
