<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string,string> */
    public $data;

    /** @param array<string,string> $data validated ContactRequest data */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo($this->data['email'], $this->data['name'])
            ->subject(__('contact.form.topics.'.$this->data['topic']).' — '.$this->data['name'])
            ->view('emails.contact-message')
            ->with([
                'data' => $this->data,
                'brand' => config('aroma.brand'),
            ]);
    }
}
