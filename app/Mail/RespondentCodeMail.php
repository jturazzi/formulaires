<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RespondentCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.verification_code_subject', ['form' => $this->form->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.respondent-code',
        );
    }
}
