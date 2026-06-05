<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AiProviderDownAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly bool $openAiHealthy,
        public readonly bool $anthropicHealthy,
        public readonly string $downTime,
    ) {}

    public function build(): self
    {
        $providers = [];
        if (!$this->openAiHealthy) $providers[] = 'OpenAI';
        if (!$this->anthropicHealthy) $providers[] = 'Anthropic';

        return $this->subject('[URGENT] AI Provider Downtime — Maids.ng Control Room')
                    ->view('emails.ai-provider-down')
                    ->with([
                        'affectedProviders' => implode(', ', $providers),
                        'downTime'          => $this->downTime,
                        'openAiStatus'      => $this->openAiHealthy ? 'Online' : 'OFFLINE',
                        'anthropicStatus'   => $this->anthropicHealthy ? 'Online' : 'OFFLINE',
                        'controlRoomUrl'    => url('/admin/control-room'),
                    ]);
    }
}
