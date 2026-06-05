<?php

namespace App\Jobs;

use App\Models\AgentOverride;
use App\Services\AgentEventLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckAiProviderHealth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 15;

    public function handle(AgentEventLogger $logger): void
    {
        $openAiHealthy    = $this->pingOpenAi();
        $anthropicHealthy = $this->pingAnthropic();
        $allDown = !$openAiHealthy && !$anthropicHealthy;

        if ($allDown) {
            AgentOverride::where('mode', 'active')->update([
                'mode'                     => 'supervised',
                'supervised_action_types'  => null,
                'override_reason'          => 'AI provider unreachable — auto-supervised',
            ]);

            foreach (AgentOverride::all() as $o) {
                $o->clearCache();
            }

            $logger->log(
                'system', 'system.ai_downtime', 'error',
                'AI providers unreachable — all agents switched to supervised mode',
                ['openai' => $openAiHealthy, 'anthropic' => $anthropicHealthy]
            );

            try {
                $adminEmail = config('mail.admin_address') ?? env('MAIL_ADMIN_ADDRESS');
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(
                        new \App\Mail\AiProviderDownAlert($openAiHealthy, $anthropicHealthy, now()->toDateTimeString())
                    );
                }
            } catch (\Exception $e) {
                Log::error('Failed to send AI downtime email: ' . $e->getMessage());
            }

        } elseif ($openAiHealthy || $anthropicHealthy) {
            $autoSupervised = AgentOverride::where('override_reason', 'LIKE', '%AI provider unreachable%')
                ->where('mode', 'supervised')
                ->count();

            if ($autoSupervised > 0) {
                $logger->log(
                    'system', 'system.ai_recovered', 'success',
                    'AI provider recovered — manually review and resume agents if needed',
                    ['openai' => $openAiHealthy, 'anthropic' => $anthropicHealthy]
                );
            }
        }
    }

    private function pingOpenAi(): bool
    {
        try {
            $key = \App\Models\Setting::get('openai_key') ?: env('OPENAI_API_KEY');
            if (!$key) return false;

            $res = Http::timeout(8)->withToken($key)
                ->get('https://api.openai.com/v1/models');
            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function pingAnthropic(): bool
    {
        try {
            $key = \App\Models\Setting::get('anthropic_key') ?: env('ANTHROPIC_API_KEY');
            if (!$key) return false;

            $res = Http::timeout(8)
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->get('https://api.anthropic.com/v1/models');
            return $res->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
