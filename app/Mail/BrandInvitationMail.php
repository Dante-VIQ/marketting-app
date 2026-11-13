<?php

namespace App\Mail;

use App\Models\BrandInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BrandInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BrandInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: "You've been invited to join {$this->invitation->brand->name} on Vumbi AI",
        );
    }

    public function content(): Content
    {
        $acceptUrl = route('invitations.accept', ['token' => $this->invitation->token]);

        return new Content(
            view: 'emails.brand-invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl'  => $acceptUrl,
                'brandName'  => $this->invitation->brand->name,
                'inviterName' => $this->invitation->inviter->name,
                'expiresAt'  => $this->invitation->expires_at->format('D, d M Y'),
            ],
        );
    }
}
