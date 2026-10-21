<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Mail\PaymentReceiptMail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Payment receipt lifecycle: issue (once per order, only for a verified
 * payment), render/store the PDF, email it.
 *
 * Duplicate protection, from cheapest to last line of defence:
 *  - OrderPaid fires once per order (CheckoutService::markOrderAsPaid locks);
 *  - one receipt per order (unique index on payment_receipts.order_id);
 *  - an atomic claim before sending, and emailed_at set only after the
 *    mailer accepted the message, so a retry after success sends nothing.
 * The one remaining window — the mailer accepted the message but the process
 * died before emailed_at was written — can at worst repeat that one email.
 */
class PaymentReceiptService
{
    /** A claim older than this is treated as abandoned (e.g. a killed worker). */
    private const CLAIM_MINUTES = 10;

    /** @var ReceiptPdfRenderer */
    private $renderer;

    public function __construct(ReceiptPdfRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * The order's receipt, created on first call. Null when the payment isn't
     * a verified, paid payment of this order — a receipt is never issued on
     * anything the gateway hasn't confirmed.
     */
    public function issue(Order $order, Payment $payment): ?PaymentReceipt
    {
        if ((int) $payment->order_id !== (int) $order->id || ! $payment->isPaid() || ! $order->isPaid()) {
            Log::warning('Payment receipt not issued: payment is not a confirmed payment of this order', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
                'order_status' => $order->status,
            ]);

            return null;
        }

        $existing = PaymentReceipt::query()->where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return PaymentReceipt::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'receipt_number' => $this->receiptNumber($order),
                'locale' => $this->localeFor($order),
            ]);
        } catch (QueryException $e) {
            // A concurrent worker created it first (unique order_id) — anything
            // else is a real failure and must surface.
            $raced = PaymentReceipt::query()->where('order_id', $order->id)->first();
            if (! $raced) {
                throw $e;
            }

            return $raced;
        }
    }

    /** The receipt PDF bytes, rendered and stored on first use. */
    public function pdf(PaymentReceipt $receipt): string
    {
        $disk = $this->disk();

        if ($receipt->pdf_path && $disk->exists($receipt->pdf_path)) {
            return (string) $disk->get($receipt->pdf_path);
        }

        $bytes = $this->renderer->render($receipt);
        if (strncmp($bytes, '%PDF', 4) !== 0) {
            throw new RuntimeException('Receipt rendering did not produce a PDF.');
        }

        $path = 'receipts/'.$receipt->receipt_number.'.pdf';
        $disk->put($path, $bytes);
        $receipt->update(['pdf_path' => $path]);

        return $bytes;
    }

    /**
     * Email the receipt to the customer. Returns whether it was sent now.
     * A mail failure is rethrown so the queued job retries it.
     */
    public function deliver(PaymentReceipt $receipt, bool $resend = false): bool
    {
        $receipt->loadMissing('order');
        $email = trim((string) $receipt->order->customer_email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            // Retrying can't fix this; record it for the admin instead.
            $receipt->update(['last_error' => 'The order has no valid customer email address.', 'failed_at' => now()]);
            Log::warning('Payment receipt not emailed: no valid customer email', ['order_id' => $receipt->order_id]);

            return false;
        }

        if (! $this->claim($receipt, $resend)) {
            return false;
        }

        try {
            Mail::to($email)->send(new PaymentReceiptMail($receipt, $this->pdf($receipt)));
        } catch (Throwable $e) {
            $receipt->update(['sending_at' => null, 'last_error' => Str::limit($e->getMessage(), 1000)]);

            throw $e;
        }

        $receipt->update([
            'emailed_to' => $email,
            'emailed_at' => now(),
            'sending_at' => null,
            'last_error' => null,
            'failed_at' => null,
        ]);

        return true;
    }

    /** The PDF as a download, never cached (it carries personal data). */
    public function download(PaymentReceipt $receipt): Response
    {
        return response($this->pdf($receipt), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->downloadName().'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The order's confirmed payment, if it has one. */
    public function paidPaymentOf(Order $order): ?Payment
    {
        return $order->payment()
            ->whereIn('status', [Payment::STATUS_CAPTURED, Payment::STATUS_AUTHORIZED])
            ->latest('id')
            ->first();
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('aroma.receipts.disk', 'local'));
    }

    /**
     * Atomically take the right to send: not already sent (unless resending)
     * and not being sent by another worker right now.
     */
    private function claim(PaymentReceipt $receipt, bool $resend): bool
    {
        $query = PaymentReceipt::query()
            ->whereKey($receipt->getKey())
            ->where(function ($q) {
                $q->whereNull('sending_at')->orWhere('sending_at', '<', now()->subMinutes(self::CLAIM_MINUTES));
            });

        if (! $resend) {
            $query->whereNull('emailed_at');
        }

        $claimed = $query->update(['sending_at' => now(), 'attempts' => DB::raw('attempts + 1')]);
        $receipt->refresh();

        return $claimed === 1;
    }

    private function receiptNumber(Order $order): string
    {
        return 'RCPT-'.preg_replace('/^AR-/', '', (string) $order->order_number);
    }

    private function localeFor(Order $order): string
    {
        $supported = array_keys((array) config('aroma.locales', []));
        $user = $order->user;

        foreach ([$order->locale, $user ? $user->locale : null, config('aroma.default_locale', 'ar')] as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                return $locale;
            }
        }

        return 'ar';
    }
}
