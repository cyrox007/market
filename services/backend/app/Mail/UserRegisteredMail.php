<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $password,
        public ?string $customSubject = null,
        public ?string $customBody = null
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customSubject ?? 'Добро пожаловать! Ваш аккаунт создан',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.user-registered',
            with: [
                'user' => $this->user,
                'password' => $this->password,
                'customBody' => $this->customBody,
            ],
        );
    }

    /**
     * Set custom body for the email.
     */
    public function setBody(string $body): self
    {
        $this->customBody = $body;
        return $this;
    }
}
