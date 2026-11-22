<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Sale;
use App\Services\InvoicePdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The invoice PDF sent to the customer, either as the invoice itself or as a payment reminder. */
class InvoiceMail extends Mailable
{
    public function __construct(public Sale $sale, public Company $company, public bool $reminder = false) {}

    public function envelope(): Envelope
    {
        $number = $this->sale->invoice->invoice_number;

        return new Envelope(subject: ($this->reminder ? 'Reminder: invoice ' : 'Invoice ').$number.' from '.$this->company->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $pdf = app(InvoicePdf::class)->make($this->sale, $this->company)->output();

        return [Attachment::fromData(fn () => $pdf, $this->sale->invoice->invoice_number.'.pdf')->withMime('application/pdf')];
    }
}
