<?php

namespace App\Mail;

use App\Models\Form;
use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Form $form,
        public Response $response,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('messages.new_response_subject', ['form' => $this->form->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-response',
        );
    }
}
