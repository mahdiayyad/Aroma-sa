<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EmailCode;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** The one-time code emailed for email + password sign-in / confirming an email. */
class EmailCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $purpose;
    public int $minutes;

    public function __construct(string $code, string $purpose, int $minutes)
    {
        $this->code = $code;
        $this->purpose = $purpose;
        $this->minutes = $minutes;
    }

    public function build()
    {
        $known = [EmailCode::PURPOSE_ADD_CREDENTIALS, EmailCode::PURPOSE_REGISTER];
        $purpose = in_array($this->purpose, $known, true) ? $this->purpose : EmailCode::PURPOSE_LOGIN;

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(__('email_auth.mail.subject.'.$purpose))
            ->view('emails.email-code')
            ->with([
                'code' => $this->code,
                'minutes' => $this->minutes,
                'purposeKey' => $purpose,
                'brand' => config('aroma.brand'),
            ]);
    }
}
