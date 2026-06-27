<?php

namespace App\Mail;

use App\Models\ChildProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class VaccinationDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChildProfile $child,
        public string $vaccineName,
        public ?int $doseNumber,
        public Carbon $dueAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Vaccination reminder for {$this->child->full_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.vaccination-due-reminder',
        );
    }
}
