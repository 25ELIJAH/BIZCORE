<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** One "something happened in your business" email. See App\Support\Notifier. */
class BusinessEventMail extends Mailable
{
    /** @param array{title: string, body: string, url: ?string} $message */
    public function __construct(public Company $company, public User $user, public array $message) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.$this->company->name.'] '.$this->message['title']);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.business-event');
    }
}
