<?php

namespace App\Jobs;

use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Services\Agents\AmbassadorAgent;
use App\Services\Agents\DTOs\InboundMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessAgentMessage Job
 * 
 * Processes an inbound message through the AmbassadorAgent asynchronously.
 * Handles the full conversation flow: identity resolution, LLM response, tool execution.
 * 
 * Usage:
 *   ProcessAgentMessage::dispatch($inboundMessage);
 */
class ProcessAgentMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job on failure.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = [10, 30, 60];

    /**
     * Maximum seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $channel,
        public string $externalId,
        public string $content,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $subject = null,
        public ?string $threadId = null,
    ) {
        $this->onQueue('agent-messages');
    }

    /**
     * Execute the job.
     */
    public function handle(AmbassadorAgent $ambassador): void
    {
        Log::info('Processing agent message', [
            'channel' => $this->channel,
            'external_id' => $this->externalId,
            'content_length' => strlen($this->content),
        ]);

        try {
            // Create the inbound message DTO
            $message = new InboundMessage(
                channel: $this->channel,
                externalId: $this->externalId,
                content: $this->content,
                phone: $this->phone,
                email: $this->email,
                subject: $this->subject,
                threadId: $this->threadId,
            );

            // Process through AmbassadorAgent
            $response = $ambassador->handle($message);

            Log::info('Agent message processed successfully', [
                'conversation_id' => $response['conversation_id'] ?? null,
                'tool_calls' => count($response['tool_calls'] ?? []),
            ]);

            // If there's a response content, dispatch a follow-up job to send it
            if (!empty($response['content'])) {
                SendAgentEmail::dispatchIf(
                    $this->channel === 'email',
                    $response['content'],
                    $this->email,
                    $response['conversation_id'] ?? null,
                    $this->subject,
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to process agent message: ' . $e->getMessage(), [
                'channel' => $this->channel,
                'external_id' => $this->externalId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}