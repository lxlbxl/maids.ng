<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeEmployerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tempPassword;

    /**
     * Create a new message instance.
     *
     * @param User $user
     * @param string|null $tempPassword  The auto-generated password (only sent on first creation)
     */
    public function __construct(User $user, ?string $tempPassword = null)
    {
        $this->user = $user;
        $this->tempPassword = $tempPassword;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Welcome to Maids.ng — Your Account is Ready!')
                    ->view('emails.welcome-employer');
    }
}
