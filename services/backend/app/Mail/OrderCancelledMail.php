<?php

namespace App\Mail;

use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Order $order,
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
            subject: $this->customSubject ?? 'Ваш заказ отменен',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.order-cancelled',
            with: [
                'order' => $this->order,
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
