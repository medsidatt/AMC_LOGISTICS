<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestExplanation extends Mailable
{
    use Queueable, SerializesModels;

    // Name of the user
    public $name;
    // Explanation request object
    public $explanation;

    /**
     * Create a new message instance.
     */
    public function __construct($name, $explanation)
    {
        $this->name = $name;
        $this->explanation = $explanation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Request Explanation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.request-explanation',
            with: [
                'name' => $this->name,
                'content' => $this->explanation->description,
                'url' => route('explanation-requests.show', $this->explanation->id),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
