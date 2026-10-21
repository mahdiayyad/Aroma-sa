<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Models\Payment;

/**
 * Display-safe payment details for a receipt, read from what the gateway
 * returned (Payment::gateway_response). Only the last four card digits are
 * ever kept: Moyasar's masked number also carries the first six (the BIN),
 * which a customer document has no reason to show.
 *
 * Sources understood:
 *  - a Moyasar payment object with a `source` (webhook path),
 *  - a Moyasar invoice with a `payments` list (customer-return path, stored
 *    under gateway_response['verification'] by CheckoutController),
 *  - BNPL gateways (Tamara), which have no card at all.
 */
final class ReceiptPaymentDetails
{
    private const BRANDS = [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'master' => 'Mastercard',
        'mada' => 'mada',
        'amex' => 'American Express',
        'american_express' => 'American Express',
        'applepay' => 'Apple Pay',
    ];

    /** @var string */
    public $methodLabel;

    /** @var string|null e.g. "Visa" */
    public $cardBrand;

    /** @var string|null last four digits only */
    public $cardLast4;

    /** @var string|null */
    public $authorizationCode;

    /** @var string|null gateway transaction / order reference */
    public $transactionReference;

    private function __construct()
    {
    }

    public static function fromPayment(Payment $payment): self
    {
        $details = new self();
        $source = self::findCardSource($payment);

        $brandKey = strtolower((string) ($source['company'] ?? ''));
        $details->cardBrand = self::BRANDS[$brandKey] ?? (self::BRANDS[strtolower((string) $payment->method)] ?? null);
        $details->cardLast4 = self::lastFour($source['number'] ?? null);
        $details->authorizationCode = self::safeCode($source['authorization_code'] ?? null);
        $details->transactionReference = self::safeCode(
            $payment->transaction_id ?: ($source['_payment_id'] ?? null) ?: $payment->reference_number,
            64
        );

        $methodKey = 'checkout.payment_methods.'.strtolower((string) $payment->method);
        $translated = __($methodKey);
        $details->methodLabel = $details->cardBrand
            ?? ($translated !== $methodKey ? $translated : ucfirst((string) $payment->method));

        return $details;
    }

    public function maskedCard(): ?string
    {
        return $this->cardLast4 !== null ? str_repeat('x', 12).$this->cardLast4 : null;
    }

    /**
     * @return array<string,mixed>|null the card `source` block, if any
     */
    private static function findCardSource(Payment $payment): ?array
    {
        $response = is_array($payment->gateway_response) ? $payment->gateway_response : [];
        $candidates = [$response];
        if (is_array($response['verification'] ?? null)) {
            $candidates[] = $response['verification'];
        }

        foreach ($candidates as $candidate) {
            if (is_array($candidate['source'] ?? null)) {
                return $candidate['source'] + ['_payment_id' => $candidate['id'] ?? null];
            }

            foreach ((array) ($candidate['payments'] ?? []) as $attempt) {
                if (is_array($attempt) && ($attempt['status'] ?? null) === 'paid' && is_array($attempt['source'] ?? null)) {
                    return $attempt['source'] + ['_payment_id' => $attempt['id'] ?? null];
                }
            }
        }

        return null;
    }

    /** @param mixed $number */
    private static function lastFour($number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        return strlen((string) $digits) >= 4 ? substr((string) $digits, -4) : null;
    }

    /** @param mixed $value */
    private static function safeCode($value, int $max = 20): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            return null;
        }

        return substr($value, 0, $max);
    }
}
