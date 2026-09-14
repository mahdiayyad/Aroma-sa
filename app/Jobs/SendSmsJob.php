<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The one genuinely async job in the app so far — everything else that
 * needs queuing (order-confirmation/payment-failed emails) uses
 * Mail::queue() from a listener, since Mail has that fluent API built in.
 * Plain SMS has no equivalent, so a real Job class is the natural fit here.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $phone;
    private string $message;

    public function __construct(string $phone, string $message)
    {
        $this->phone = $phone;
        $this->message = $message;
    }

    public function handle(SmsService $sms): void
    {
        $sms->send($this->phone, $this->message);
    }
}
