<?php

namespace App\Mail;

use App\Models\StandaloneVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $verification;

    /**
     * Create a new message instance.
     *
     * @param StandaloneVerification $verification
     */
    public function __construct(StandaloneVerification $verification)
    {
        $this->verification = $verification;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Identity Verification Report — Maids.ng')
                    ->view('emails.verification-report');
    }
}
