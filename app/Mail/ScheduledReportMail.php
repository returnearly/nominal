<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ScheduledReport;
use App\Reports\UptimeReportSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ScheduledReport $report,
        public UptimeReportSummary $summary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->report->name.': '.$this->summary->periodLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.scheduled-report',
        );
    }
}
