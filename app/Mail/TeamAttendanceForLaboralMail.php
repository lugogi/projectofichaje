<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamAttendanceForLaboralMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public array $context,
        public string $excelBinary,
        public string $fileName,
        public string $destinatario,
        public string $remitente,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Registros de plantilla — '.($this->context['mes_label'] ?? ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.team-attendance-laboral',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->excelBinary, $this->fileName)
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
