<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Traits\Localizable;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * Renders a payment receipt to PDF bytes in the receipt's language.
 *
 * mPDF rather than Dompdf: customer names and addresses are often Arabic,
 * which needs real glyph shaping and right-to-left layout — Dompdf prints
 * Arabic as disconnected letters. Pure PHP, so nothing to install on the
 * server beyond the Composer package.
 */
class ReceiptPdfRenderer
{
    use Localizable;

    public function render(PaymentReceipt $receipt): string
    {
        return $this->withLocale($receipt->locale, function () use ($receipt): string {
            $receipt->loadMissing(['order.items', 'payment']);
            $rtl = $receipt->locale === 'ar';

            $html = view('receipts.payment', [
                'receipt' => $receipt,
                'order' => $receipt->order,
                'payment' => ReceiptPaymentDetails::fromPayment($receipt->payment),
                'brand' => config('aroma.brand'),
                'rtl' => $rtl,
            ])->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 14,
                'margin_bottom' => 14,
                'margin_left' => 14,
                'margin_right' => 14,
                'default_font' => 'dejavusans',
                'tempDir' => $this->tempDir(),
                // Arabic runs pick an Arabic-capable font and get shaped.
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
            ]);

            $mpdf->SetTitle(__('receipts.document_title', ['number' => $receipt->receipt_number]));
            $mpdf->SetAuthor((string) config('aroma.brand.name'));
            $mpdf->SetCreator((string) config('aroma.brand.name'));
            if ($rtl) {
                $mpdf->SetDirectionality('rtl');
            }

            $mpdf->WriteHTML($html);

            return $mpdf->Output('', Destination::STRING_RETURN);
        });
    }

    private function tempDir(): string
    {
        $dir = storage_path('framework/cache/mpdf');
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
