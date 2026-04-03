<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulletinMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct($pdfContent, $employee, $dateRange)
    {
        $this->pdfContent = $pdfContent;
        $this->employee = $employee;
        $this->dateRange = $dateRange;
    }

    public function build()
    {
        $filename = 'BP_'
            . $this->dateRange
            . '_'
            . $this->employee['name'] . '.pdf';

        return $this->view('pages.payrolls.emails.bulletin')
            ->with([
                'employee' => $this->employee,
                'date_range' => $this->dateRange,
            ])
            ->attachData(
                $this->pdfContent,
                $filename,
                [
                    'mime' => 'application/pdf',
                ]
            );
    }

}

