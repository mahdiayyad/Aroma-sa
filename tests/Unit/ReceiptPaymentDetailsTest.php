<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Payment;
use App\Services\Receipts\ReceiptPaymentDetails;
use Tests\TestCase;

class ReceiptPaymentDetailsTest extends TestCase
{
    private function payment(array $attributes): Payment
    {
        return new Payment(array_merge([
            'gateway' => 'moyasar',
            'method' => 'visa',
            'status' => Payment::STATUS_CAPTURED,
            'amount' => 100,
        ], $attributes));
    }

    public function test_a_moyasar_payment_source_gives_brand_last_four_and_auth_code(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment([
            'transaction_id' => 'pay_123',
            'gateway_response' => ['id' => 'pay_123', 'source' => [
                'company' => 'mastercard', 'number' => '5123-45XX-XXXX-0123', 'authorization_code' => '623116',
            ]],
        ]));

        $this->assertSame('Mastercard', $details->methodLabel);
        $this->assertSame('0123', $details->cardLast4);
        $this->assertSame('xxxxxxxxxxxx0123', $details->maskedCard());
        $this->assertSame('623116', $details->authorizationCode);
        $this->assertSame('pay_123', $details->transactionReference);
    }

    public function test_the_card_bin_is_never_exposed(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment([
            'gateway_response' => ['source' => ['company' => 'visa', 'number' => '4111-11XX-XXXX-1111']],
        ]));

        $this->assertStringNotContainsString('411111', (string) $details->maskedCard());
        $this->assertSame('xxxxxxxxxxxx1111', $details->maskedCard());
    }

    public function test_a_verified_invoice_on_the_return_path_is_read_from_its_paid_payment(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment([
            'method' => 'mada',
            'gateway_response' => ['verification' => ['id' => 'inv_1', 'status' => 'paid', 'payments' => [
                ['id' => 'pay_failed', 'status' => 'failed', 'source' => ['company' => 'visa', 'number' => '4111-11XX-XXXX-9999']],
                ['id' => 'pay_ok', 'status' => 'paid', 'source' => ['company' => 'mada', 'number' => '4464-04XX-XXXX-4242', 'authorization_code' => 'A1B2C3']],
            ]]],
        ]));

        $this->assertSame('mada', $details->methodLabel);
        $this->assertSame('4242', $details->cardLast4);
        $this->assertSame('A1B2C3', $details->authorizationCode);
        $this->assertSame('pay_ok', $details->transactionReference);
    }

    public function test_bnpl_has_no_card_and_uses_its_reference(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment([
            'gateway' => 'tamara',
            'method' => 'tamara',
            'status' => Payment::STATUS_AUTHORIZED,
            'reference_number' => 'tam-order-77',
            'gateway_response' => ['order_id' => 'tam-order-77'],
        ]));

        $this->assertSame(__('checkout.payment_methods.tamara'), $details->methodLabel);
        $this->assertNull($details->maskedCard());
        $this->assertNull($details->authorizationCode);
        $this->assertSame('tam-order-77', $details->transactionReference);
    }

    public function test_unexpected_characters_in_gateway_codes_are_dropped(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment([
            'transaction_id' => '<script>x</script>',
            'gateway_response' => ['source' => ['company' => 'visa', 'number' => '4111', 'authorization_code' => '12 34<b>']],
        ]));

        $this->assertNull($details->authorizationCode);
        $this->assertNull($details->transactionReference);
        $this->assertSame('4111', $details->cardLast4);
    }

    public function test_no_gateway_data_falls_back_to_the_chosen_method(): void
    {
        $details = ReceiptPaymentDetails::fromPayment($this->payment(['method' => 'applepay', 'gateway_response' => null]));

        $this->assertSame('Apple Pay', $details->methodLabel);
        $this->assertNull($details->cardLast4);
    }
}
