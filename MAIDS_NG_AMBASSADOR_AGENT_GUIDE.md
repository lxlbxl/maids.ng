# Maids.ng — Ambassador SDR/Support Agent
## Technical Implementation Guide — All Phases

**Version:** 1.0  
**Prerequisite:** Phase 0 (Knowledge Base & Prompt Management) must be complete and all tests passing.  
**Stack:** Laravel 11, PHP 8.2+, MySQL, React 18, Inertia.js, Tailwind CSS, Reverb/Polling for real-time  
**External Services:** OpenAI / Anthropic, Meta Cloud API (WA + IG + FB), SMTP (Postmark / Brevo / Gmail SMTP), Termii (OTP SMS)

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Phase 1 — Core Agent + Web Chat Widget](#phase-1--core-agent--web-chat-widget)
- [Phase 2 — Email Channel (SMTP Send + Receive)](#phase-2--email-channel-smtp-send--receive)
- [Phase 3 — WhatsApp Channel (Meta Cloud API)](#phase-3--whatsapp-channel-meta-cloud-api)
- [Phase 4 — Instagram & Facebook DM (Meta Graph API)](#phase-4--instagram--facebook-dm-meta-graph-api)
- [Phase 5 — Identity Resolution, OTP Auth & Tool Execution](#phase-5--identity-resolution-otp-auth--tool-execution)
- [Phase 6 — Admin Conversation Dashboard](#phase-6--admin-conversation-dashboard)
- [Global Definition of Done](#global-definition-of-done)

---

## Architecture Overview

### What the Ambassador Agent Is

A single LLM-powered agent that is the **front door** to Maids.ng across every channel. It:

- Handles guests (unauthenticated) with scoped, restricted responses
- Handles authenticated members with full account-aware support
- Routes all channels (web, email, WhatsApp, IG DM, FB DM) through a single brain
- Executes tools: creates users, creates maid requests, triggers matching, escalates to humans
- Logs every conversation with full context so admins can review, intervene, and audit

### What It Is NOT

- It does not replace or modify ScoutAgent, SentinelAgent, or any existing agent
- It does not touch the employer quiz or dashboard flows
- It does not have its own LLM client — it reuses `AiService` (already in codebase)
- It is not a chatbot with fixed decision trees — it is LLM-native, context-driven

### Data Flow (all channels)

```
Inbound message (any channel)
        ↓
Channel Handler (parses raw payload → normalised ChannelMessage DTO)
        ↓
IdentityResolver (find or create AgentChannelIdentity + AgentConversation)
        ↓
ConversationManager (load last N messages as history)
        ↓
AmbassadorAgent::reply() 
  → KnowledgeService::buildContext(tier)
  → AiService::chat(systemPrompt, history, tools)
  → ToolDispatcher::dispatch() if tool_call in response
  → AiService::chat() again with tool result
        ↓
ConversationManager::save(user_msg, assistant_msg, tool_calls)
        ↓
Channel Sender (sends reply back via correct channel API)
        ↓
Message stored, conversation updated
```

### New Files Summary (all phases)

```
app/
  Agents/
    AmbassadorAgent.php
    Tools/
      UserTools.php
      MatchingTools.php
      SupportTools.php
      AuthTools.php
    ToolDispatcher.php

  DTOs/
    ChannelMessage.php

  Services/
    IdentityResolver.php
    ConversationManager.php
    ChannelSender.php
    EmailPoller.php

  Channels/
    WebChatChannel.php
    EmailChannel.php
    WhatsAppChannel.php
    MetaDMChannel.php

  Http/Controllers/Agent/
    WebChatController.php
    EmailInboundController.php
    WhatsAppWebhookController.php
    MetaWebhookController.php
    AdminConversationController.php

  Models/
    AgentConversation.php
    AgentMessage.php
    AgentChannelIdentity.php
    AgentLead.php

  Jobs/
    ProcessAgentMessage.php
    PollInboundEmail.php
    SendAgentEmail.php

database/migrations/
  ..._create_agent_conversations_table.php
  ..._create_agent_messages_table.php
  ..._create_agent_channel_identities_table.php
  ..._create_agent_leads_table.php

routes/
  agent.php

resources/js/
  Components/AgentChatWidget.jsx
  Pages/Admin/Agent/Conversations/Index.jsx
  Pages/Admin/Agent/Conversations/Show.jsx

config/
  ambassador.php
```

---

## Phase 1 — Core Agent + Web Chat Widget

**Goal:** The Ambassador Agent works on the web homepage and on the logged-in dashboard. No external channels yet. Admin can see all conversations.

**Deployable independently:** Yes. Phase 1 can go live before Phases 2–5.

---

### 1.1 — Database Migrations

Create these four files in `database/migrations/`. Timestamps must be sequential.

---

#### `agent_channel_identities`

**File:** `database/migrations/2026_04_29_000001_create_agent_channel_identities_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_channel_identities', function (Blueprint $table) {
            $table->id();

            // The channel this identity was first seen on.
            // 'web'       = browser session
            // 'email'     = inbound email address
            // 'whatsapp'  = WhatsApp phone number (E.164 format)
            // 'instagram' = Instagram Page-Scoped ID (PSID)
            // 'facebook'  = Facebook Page-Scoped ID (PSID)
            $table->enum('channel', ['web', 'email', 'whatsapp', 'instagram', 'facebook']);

            // The unique identifier on that channel.
            // web:        Laravel session ID (from cookie)
            // email:      the sender's email address (lowercased)
            // whatsapp:   phone in E.164 format e.g. +2348012345678
            // instagram:  PSID string
            // facebook:   PSID string
            $table->string('external_id', 512);

            // Linked platform user — null until the identity is verified/authenticated.
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Collected during conversation — may not match user record fields yet
            $table->string('display_name', 255)->nullable();
            $table->string('phone', 30)->nullable();     // normalised E.164
            $table->string('email', 255)->nullable();

            // OTP verification state (used for external channel auth)
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);

            // Metadata for the channel (e.g. WA display name, IG username)
            $table->json('channel_meta')->nullable();

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // An identity is unique per channel+external_id pair.
            $table->unique(['channel', 'external_id']);
            $table->index('user_id');
            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_channel_identities');
    }
};
```

---

#### `agent_conversations`

**File:** `database/migrations/2026_04_29_000002_create_agent_conversations_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_identity_id')
                  ->constrained('agent_channel_identities')
                  ->cascadeOnDelete();

            // Denormalised for fast query — kept in sync with identity
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->enum('channel', ['web', 'email', 'whatsapp', 'instagram', 'facebook']);

            // open        = active conversation
            // resolved    = agent or admin closed it
            // escalated   = handed to human agent
            // converted   = guest became a registered user during this conversation
            // spam        = flagged as spam (email channel)
            $table->enum('status', ['open', 'resolved', 'escalated', 'converted', 'spam'])
                  ->default('open');

            // AI-generated one-line summary, updated at end of each reply cycle
            $table->string('intent_summary', 500)->nullable();

            // For email channel: the email subject line of the thread
            $table->string('email_subject', 500)->nullable();

            // For email channel: the Message-ID of the original email to thread replies
            $table->string('email_thread_id', 500)->nullable();

            // Admin note if manually escalated
            $table->text('admin_note')->nullable();

            // ID of the admin user who took over this conversation, if escalated
            $table->foreignId('assigned_to')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['channel_identity_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['channel', 'status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_conversations');
    }
};
```

---

#### `agent_messages`

**File:** `database/migrations/2026_04_29_000003_create_agent_messages_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                  ->constrained('agent_conversations')
                  ->cascadeOnDelete();

            // 'user'      = message from the human
            // 'assistant' = message from the LLM / Ambassador
            // 'tool'      = tool execution result (not shown to user)
            // 'system'    = system-injected message (not shown to user)
            // 'admin'     = manual reply from admin staff
            $table->enum('role', ['user', 'assistant', 'tool', 'system', 'admin']);

            $table->longText('content');

            // If the assistant called a tool, store the call + result here for context replay
            $table->json('tool_call')->nullable();
            // Structure: { "name": "create_employer_account", "args": {...}, "result": {...} }

            // Channel-specific message ID (WA message_id, email Message-ID header, etc.)
            // Used for deduplication and threading
            $table->string('external_message_id', 500)->nullable();

            // Token count for cost tracking (populated after LLM call)
            $table->unsignedInteger('tokens_used')->nullable();

            // True if this message has been read by admin in the conversation view
            $table->boolean('admin_read')->default(false);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index('external_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
```

---

#### `agent_leads`

**File:** `database/migrations/2026_04_29_000004_create_agent_leads_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_identity_id')
                  ->constrained('agent_channel_identities')
                  ->cascadeOnDelete();

            // If this lead converts, link to the created user
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Collected by the agent during conversation
            $table->string('name', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();

            // Structured intent data collected during conversation
            // e.g. { "help_type": "full_time_maid", "location": "Lekki", "budget": "50000" }
            $table->json('intent')->nullable();

            // new       = just started chatting
            // warm      = has expressed clear intent to use the platform
            // registered = has created an account (may link to user_id)
            // lost      = disengaged without converting
            $table->enum('status', ['new', 'warm', 'registered', 'lost'])->default('new');

            // Free-text notes added by admin from the conversation view
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_leads');
    }
};
```

Run all migrations:

```bash
php artisan migrate
php artisan migrate:status
```

All four tables must show as `Ran`.

---

### 1.2 — Eloquent Models

#### `AgentChannelIdentity`

**File:** `app/Models/AgentChannelIdentity.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AgentChannelIdentity extends Model
{
    protected $fillable = [
        'channel', 'external_id', 'user_id', 'display_name',
        'phone', 'email', 'otp', 'otp_expires_at',
        'is_verified', 'channel_meta', 'last_seen_at',
    ];

    protected $casts = [
        'channel_meta'   => 'array',
        'is_verified'    => 'boolean',
        'otp_expires_at' => 'datetime',
        'last_seen_at'   => 'datetime',
    ];

    protected $hidden = ['otp']; // Never serialize OTP in API responses

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AgentConversation::class, 'channel_identity_id');
    }

    public function lead(): HasOne
    {
        return $this->hasOne(AgentLead::class, 'channel_identity_id');
    }

    public function activeConversation(): HasOne
    {
        return $this->hasOne(AgentConversation::class, 'channel_identity_id')
                    ->where('status', 'open')
                    ->latestOfMany();
    }

    public function isOtpValid(string $otp): bool
    {
        return $this->otp === $otp
            && $this->otp_expires_at
            && $this->otp_expires_at->isFuture();
    }

    /** Determine the tier string for KnowledgeService */
    public function getTier(): string
    {
        if ($this->user_id && $this->is_verified) {
            return 'authenticated';
        }
        if ($this->channel === 'web') {
            return 'guest'; // web guests are never 'lead'
        }
        return 'lead'; // external channels without verified user link
    }
}
```

---

#### `AgentConversation`

**File:** `app/Models/AgentConversation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentConversation extends Model
{
    protected $fillable = [
        'channel_identity_id', 'user_id', 'channel', 'status',
        'intent_summary', 'email_subject', 'email_thread_id',
        'admin_note', 'assigned_to', 'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(AgentChannelIdentity::class, 'channel_identity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id');
    }

    /**
     * Return the last N messages in LLM-ready format.
     * Excludes 'tool' and 'system' roles from history to reduce token usage.
     * Tool results are embedded in the assistant message via tool_call JSON.
     */
    public function getHistory(int $limit = 20): array
    {
        return $this->messages()
            ->whereIn('role', ['user', 'assistant', 'admin'])
            ->orderByDesc('created_at')
            ->take($limit)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role'    => $m->role === 'admin' ? 'assistant' : $m->role,
                'content' => $m->content,
            ])
            ->values()
            ->toArray();
    }
}
```

---

#### `AgentMessage`

**File:** `app/Models/AgentMessage.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'conversation_id', 'role', 'content', 'tool_call',
        'external_message_id', 'tokens_used', 'admin_read',
    ];

    protected $casts = [
        'tool_call'  => 'array',
        'admin_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class);
    }
}
```

---

#### `AgentLead`

**File:** `app/Models/AgentLead.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentLead extends Model
{
    protected $fillable = [
        'channel_identity_id', 'user_id', 'name', 'phone',
        'email', 'intent', 'status', 'notes',
    ];

    protected $casts = ['intent' => 'array'];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(AgentChannelIdentity::class, 'channel_identity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

### 1.3 — ChannelMessage DTO

A normalised data transfer object that all channel handlers produce. This is what `AmbassadorAgent` receives regardless of channel.

**File:** `app/DTOs/ChannelMessage.php`

```php
<?php

namespace App\DTOs;

class ChannelMessage
{
    public function __construct(
        public readonly string  $channel,       // 'web'|'email'|'whatsapp'|'instagram'|'facebook'
        public readonly string  $externalId,    // the sender's unique ID on that channel
        public readonly string  $content,       // the plain text message content
        public readonly ?string $displayName,   // sender's display name if available
        public readonly ?string $phone,         // E.164 phone if available
        public readonly ?string $email,         // sender email if available
        public readonly ?string $externalMsgId, // channel-native message ID for dedup
        public readonly array   $meta = [],     // any channel-specific extras
    ) {}
}
```

---

### 1.4 — IdentityResolver Service

Finds or creates an `AgentChannelIdentity` and its linked `AgentConversation`.

**File:** `app/Services/IdentityResolver.php`

```php
<?php

namespace App\Services;

use App\DTOs\ChannelMessage;
use App\Models\AgentChannelIdentity;
use App\Models\AgentConversation;
use App\Models\AgentLead;

class IdentityResolver
{
    /**
     * Given a normalised ChannelMessage, return:
     *  - the AgentChannelIdentity (found or created)
     *  - the active AgentConversation (found or created)
     *
     * @return array{identity: AgentChannelIdentity, conversation: AgentConversation}
     */
    public function resolve(ChannelMessage $message): array
    {
        $identity = $this->resolveIdentity($message);
        $conversation = $this->resolveConversation($identity, $message);

        return compact('identity', 'conversation');
    }

    private function resolveIdentity(ChannelMessage $message): AgentChannelIdentity
    {
        $identity = AgentChannelIdentity::firstOrCreate(
            [
                'channel'     => $message->channel,
                'external_id' => $message->externalId,
            ],
            [
                'display_name' => $message->displayName,
                'phone'        => $message->phone,
                'email'        => $message->email,
                'channel_meta' => $message->meta,
            ]
        );

        // Update last seen and any newly available fields
        $updates = ['last_seen_at' => now()];

        if ($message->displayName && !$identity->display_name) {
            $updates['display_name'] = $message->displayName;
        }
        if ($message->phone && !$identity->phone) {
            $updates['phone'] = $message->phone;
        }
        if ($message->email && !$identity->email) {
            $updates['email'] = $message->email;
        }

        $identity->update($updates);

        // Ensure a lead record exists for non-authenticated identities
        if (!$identity->user_id) {
            AgentLead::firstOrCreate(
                ['channel_identity_id' => $identity->id],
                [
                    'name'   => $message->displayName,
                    'phone'  => $message->phone,
                    'email'  => $message->email,
                    'status' => 'new',
                ]
            );
        }

        return $identity->fresh();
    }

    private function resolveConversation(
        AgentChannelIdentity $identity,
        ChannelMessage $message
    ): AgentConversation {
        // For email, thread conversations by subject/thread-id
        if ($message->channel === 'email') {
            return $this->resolveEmailConversation($identity, $message);
        }

        // For all other channels: one open conversation per identity at a time.
        // If the last open conversation is older than 24 hours with no activity, start fresh.
        $existing = $identity->activeConversation;

        if ($existing) {
            $idleHours = $existing->last_message_at
                ? $existing->last_message_at->diffInHours(now())
                : 0;

            if ($idleHours < 24) {
                return $existing;
            }

            // Close the idle conversation
            $existing->update(['status' => 'resolved']);
        }

        return AgentConversation::create([
            'channel_identity_id' => $identity->id,
            'user_id'             => $identity->user_id,
            'channel'             => $message->channel,
            'status'              => 'open',
            'last_message_at'     => now(),
        ]);
    }

    private function resolveEmailConversation(
        AgentChannelIdentity $identity,
        ChannelMessage $message
    ): AgentConversation {
        $threadId = $message->meta['thread_id'] ?? null;
        $subject  = $message->meta['subject'] ?? 'Support Request';

        // Try to find an existing open email conversation with this thread ID
        if ($threadId) {
            $existing = AgentConversation::where('channel_identity_id', $identity->id)
                ->where('email_thread_id', $threadId)
                ->where('status', 'open')
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return AgentConversation::create([
            'channel_identity_id' => $identity->id,
            'user_id'             => $identity->user_id,
            'channel'             => 'email',
            'status'              => 'open',
            'email_subject'       => $subject,
            'email_thread_id'     => $threadId,
            'last_message_at'     => now(),
        ]);
    }

    /**
     * Link an authenticated user to an existing identity.
     * Called after OTP verification or web login.
     */
    public function linkUser(AgentChannelIdentity $identity, int $userId): void
    {
        $identity->update([
            'user_id'     => $userId,
            'is_verified' => true,
            'otp'         => null,
            'otp_expires_at' => null,
        ]);

        // Also update any open conversations so admin can see them as authenticated
        AgentConversation::where('channel_identity_id', $identity->id)
            ->where('status', 'open')
            ->update(['user_id' => $userId]);

        // Mark lead as converted
        AgentLead::where('channel_identity_id', $identity->id)
            ->update(['user_id' => $userId, 'status' => 'registered']);
    }
}
```

---

### 1.5 — ConversationManager Service

Handles message persistence and history retrieval.

**File:** `app/Services/ConversationManager.php`

```php
<?php

namespace App\Services;

use App\Models\AgentConversation;
use App\Models\AgentMessage;

class ConversationManager
{
    // Max number of prior messages to include as history in each LLM call.
    // Higher = more context, more tokens, higher cost. 20 is a balanced default.
    private const HISTORY_LIMIT = 20;

    public function saveUserMessage(
        AgentConversation $conversation,
        string $content,
        ?string $externalMessageId = null
    ): AgentMessage {
        // Deduplicate: if we've already seen this external message ID, skip
        if ($externalMessageId) {
            $existing = AgentMessage::where('external_message_id', $externalMessageId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $message = AgentMessage::create([
            'conversation_id'    => $conversation->id,
            'role'               => 'user',
            'content'            => $content,
            'external_message_id' => $externalMessageId,
            'created_at'         => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    public function saveAssistantMessage(
        AgentConversation $conversation,
        string $content,
        ?array $toolCall = null,
        ?int $tokensUsed = null
    ): AgentMessage {
        return AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'assistant',
            'content'         => $content,
            'tool_call'       => $toolCall,
            'tokens_used'     => $tokensUsed,
            'created_at'      => now(),
        ]);
    }

    public function getHistory(AgentConversation $conversation): array
    {
        return $conversation->getHistory(self::HISTORY_LIMIT);
    }

    /**
     * Update the AI-generated intent summary on the conversation.
     * Called after each assistant reply to keep the admin view current.
     */
    public function updateIntentSummary(AgentConversation $conversation, string $summary): void
    {
        $conversation->update(['intent_summary' => $summary]);
    }
}
```

---

### 1.6 — Tool Definitions

Each Tool class is a collection of static methods that the `ToolDispatcher` calls by name. These are also what you pass to the LLM as tool schemas.

#### Tool Schema Constants

**File:** `app/Agents/Tools/ToolSchemas.php`

```php
<?php

namespace App\Agents\Tools;

class ToolSchemas
{
    /**
     * Returns the array of tool definitions passed to the LLM.
     * Each definition follows the OpenAI function calling format.
     * Only pass tools appropriate for the tier (see AmbassadorAgent::buildToolset).
     */
    public static function guest(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'collect_lead_info',
                    'description' => 'Save contact information volunteered by a guest during conversation.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'name'  => ['type' => 'string', 'description' => 'Full name'],
                            'phone' => ['type' => 'string', 'description' => 'Phone number'],
                            'email' => ['type' => 'string', 'description' => 'Email address'],
                            'intent_notes' => ['type' => 'string', 'description' => 'What the guest is looking for'],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'generate_registration_link',
                    'description' => 'Generate and return a registration link for a guest who wants to sign up.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'role' => [
                                'type' => 'string',
                                'enum' => ['employer', 'maid'],
                                'description' => 'The account type the guest wants to create',
                            ],
                        ],
                        'required' => ['role'],
                    ],
                ],
            ],
        ];
    }

    public static function authenticated(): array
    {
        return array_merge(self::guest(), [
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_account_summary',
                    'description' => 'Fetch the current user\'s account summary: active assignments, wallet balance, and recent activity.',
                    'parameters'  => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'create_maid_request',
                    'description' => 'Create a new maid matching request for the authenticated employer.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'help_type'  => ['type' => 'string', 'description' => 'Type of domestic help needed'],
                            'location'   => ['type' => 'string', 'description' => 'Location/area in Lagos'],
                            'budget'     => ['type' => 'integer', 'description' => 'Monthly budget in Naira'],
                            'schedule'   => ['type' => 'string', 'description' => 'full_time or part_time'],
                            'urgency'    => ['type' => 'string', 'description' => 'asap, this_week, this_month'],
                        ],
                        'required' => ['help_type', 'location', 'budget'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'get_assignment_status',
                    'description' => 'Get the status of active or recent maid assignments for the current user.',
                    'parameters'  => ['type' => 'object', 'properties' => []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'escalate_to_human',
                    'description' => 'Escalate the conversation to a human support agent. Use when the query is outside your capability or the user requests a human.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'reason' => ['type' => 'string', 'description' => 'Reason for escalation'],
                        ],
                        'required' => ['reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'send_otp',
                    'description' => 'Send a one-time password to a phone number for identity verification on external channels.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'phone' => ['type' => 'string', 'description' => 'E.164 format phone number'],
                        ],
                        'required' => ['phone'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name'        => 'verify_otp',
                    'description' => 'Verify a one-time password entered by the user to authenticate their identity.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'phone' => ['type' => 'string'],
                            'otp'   => ['type' => 'string'],
                        ],
                        'required' => ['phone', 'otp'],
                    ],
                ],
            ],
        ]);
    }
}
```

---

#### Tool Implementation Classes

**File:** `app/Agents/Tools/UserTools.php`

```php
<?php

namespace App\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Models\AgentLead;
use App\Models\User;

class UserTools
{
    public static function collectLeadInfo(
        AgentChannelIdentity $identity,
        array $args
    ): array {
        $updates = array_filter([
            'display_name' => $args['name'] ?? null,
            'phone'        => $args['phone'] ?? null,
            'email'        => $args['email'] ?? null,
        ]);

        if (!empty($updates)) {
            $identity->update($updates);
        }

        // Update the lead record
        $lead = $identity->lead;
        if ($lead) {
            $leadUpdates = array_filter([
                'name'  => $args['name'] ?? null,
                'phone' => $args['phone'] ?? null,
                'email' => $args['email'] ?? null,
            ]);
            if (isset($args['intent_notes'])) {
                $intent = $lead->intent ?? [];
                $intent['notes'] = $args['intent_notes'];
                $leadUpdates['intent'] = $intent;
                $leadUpdates['status'] = 'warm';
            }
            $lead->update($leadUpdates);
        }

        return ['saved' => true, 'message' => 'Contact information saved.'];
    }

    public static function generateRegistrationLink(array $args): array
    {
        $role = $args['role'] ?? 'employer';
        $url  = url("/register?role={$role}");
        return ['url' => $url, 'role' => $role];
    }

    public static function getAccountSummary(User $user): array
    {
        // Adapt these to match your actual relationship/method names
        return [
            'name'               => $user->name,
            'email'              => $user->email,
            'role'               => $user->getRoleNames()->first(),
            'wallet_balance'     => $user->wallet?->balance ?? 0,
            'active_assignments' => $user->employerAssignments()
                                         ->where('status', 'active')
                                         ->count(),
            'pending_payments'   => $user->pendingPayments()->count() ?? 0,
        ];
    }
}
```

**File:** `app/Agents/Tools/MatchingTools.php`

```php
<?php

namespace App\Agents\Tools;

use App\Models\User;
use App\Agents\ScoutAgent;
use App\Services\KnowledgeService;

class MatchingTools
{
    public static function createMaidRequest(User $user, array $args): array
    {
        // Validate the user is an employer
        if (!$user->hasRole('employer')) {
            return ['error' => 'Only employer accounts can create maid requests.'];
        }

        // Create the preference/request record — adapt model name to match your codebase
        $preference = $user->matchingPreferences()->create([
            'help_type' => $args['help_type'],
            'location'  => $args['location'],
            'budget'    => $args['budget'],
            'schedule'  => $args['schedule'] ?? 'full_time',
            'urgency'   => $args['urgency'] ?? 'this_week',
        ]);

        return [
            'preference_id' => $preference->id,
            'message'       => 'Request created. Finding matches now.',
        ];
    }

    public static function getAssignmentStatus(User $user): array
    {
        $assignments = $user->employerAssignments()
            ->with('maid:id,name,phone')
            ->latest()
            ->take(3)
            ->get(['id', 'status', 'start_date', 'maid_id'])
            ->map(fn($a) => [
                'id'         => $a->id,
                'status'     => $a->status,
                'start_date' => $a->start_date?->format('d M Y'),
                'maid_name'  => $a->maid?->name,
            ])
            ->toArray();

        return ['assignments' => $assignments];
    }
}
```

**File:** `app/Agents/Tools/SupportTools.php`

```php
<?php

namespace App\Agents\Tools;

use App\Models\AgentConversation;

class SupportTools
{
    public static function escalateToHuman(
        AgentConversation $conversation,
        array $args
    ): array {
        $conversation->update([
            'status'     => 'escalated',
            'admin_note' => $args['reason'] ?? 'User requested human support.',
        ]);

        // TODO Phase 6: Notify admin via email/Slack when a conversation is escalated

        return [
            'escalated' => true,
            'message'   => 'A human support agent has been notified and will respond shortly.',
        ];
    }
}
```

**File:** `app/Agents/Tools/AuthTools.php`

```php
<?php

namespace App\Agents\Tools;

use App\Models\AgentChannelIdentity;
use App\Models\User;
use App\Services\IdentityResolver;
use Illuminate\Support\Str;

class AuthTools
{
    public static function sendOtp(AgentChannelIdentity $identity, array $args): array
    {
        $phone = $args['phone'];

        // Check if a user exists with this phone
        $user = User::where('phone', $phone)->first();
        if (!$user) {
            return [
                'sent'    => false,
                'message' => 'No account found with that phone number. Would you like to register instead?',
            ];
        }

        $otp = (string) random_int(100000, 999999);

        $identity->update([
            'otp'            => $otp,
            'otp_expires_at' => now()->addMinutes(10),
            'phone'          => $phone,
        ]);

        // Send via Termii (already configured in your codebase)
        // Replace with your actual Termii/SMS service call
        app(\App\Services\SmsService::class)->send($phone, "Your Maids.ng verification code is: {$otp}. Valid for 10 minutes.");

        return ['sent' => true, 'message' => 'OTP sent. Please enter the 6-digit code.'];
    }

    public static function verifyOtp(
        AgentChannelIdentity $identity,
        IdentityResolver $resolver,
        array $args
    ): array {
        if (!$identity->isOtpValid($args['otp'])) {
            return ['verified' => false, 'message' => 'Invalid or expired code. Please try again or request a new code.'];
        }

        $user = User::where('phone', $args['phone'])->first();
        if (!$user) {
            return ['verified' => false, 'message' => 'Account not found.'];
        }

        $resolver->linkUser($identity, $user->id);

        return [
            'verified' => true,
            'user_id'  => $user->id,
            'name'     => $user->name,
            'message'  => "Verified! Welcome back, {$user->name}.",
        ];
    }
}
```

---

### 1.7 — ToolDispatcher

**File:** `app/Agents/ToolDispatcher.php`

```php
<?php

namespace App\Agents;

use App\Agents\Tools\{AuthTools, MatchingTools, SupportTools, UserTools};
use App\Models\{AgentChannelIdentity, AgentConversation, User};
use App\Services\IdentityResolver;

class ToolDispatcher
{
    public function __construct(
        private AgentChannelIdentity $identity,
        private AgentConversation $conversation,
        private ?User $user,
        private IdentityResolver $resolver,
    ) {}

    /**
     * Execute a tool call returned by the LLM.
     *
     * @param string $toolName  The function name the LLM invoked
     * @param array  $args      The parsed arguments
     * @return array            The tool result to feed back to the LLM
     */
    public function dispatch(string $toolName, array $args): array
    {
        return match ($toolName) {
            'collect_lead_info'        => UserTools::collectLeadInfo($this->identity, $args),
            'generate_registration_link' => UserTools::generateRegistrationLink($args),
            'get_account_summary'      => $this->requireUser(fn() => UserTools::getAccountSummary($this->user)),
            'create_maid_request'      => $this->requireUser(fn() => MatchingTools::createMaidRequest($this->user, $args)),
            'get_assignment_status'    => $this->requireUser(fn() => MatchingTools::getAssignmentStatus($this->user)),
            'escalate_to_human'        => SupportTools::escalateToHuman($this->conversation, $args),
            'send_otp'                 => AuthTools::sendOtp($this->identity, $args),
            'verify_otp'               => AuthTools::verifyOtp($this->identity, $this->resolver, $args),
            default                    => ['error' => "Unknown tool: {$toolName}"],
        };
    }

    private function requireUser(callable $fn): array
    {
        if (!$this->user) {
            return ['error' => 'This action requires an authenticated account. Please log in or verify your identity.'];
        }
        return $fn();
    }
}
```

---

### 1.8 — AmbassadorAgent

**File:** `app/Agents/AmbassadorAgent.php`

```php
<?php

namespace App\Agents;

use App\Agents\Tools\ToolSchemas;
use App\DTOs\ChannelMessage;
use App\Models\{AgentChannelIdentity, AgentConversation, User};
use App\Services\{ConversationManager, IdentityResolver, KnowledgeService};
use App\Services\AiService; // your existing AI service

class AmbassadorAgent
{
    // Max tool call iterations per reply to prevent runaway loops
    private const MAX_TOOL_ITERATIONS = 3;

    public function __construct(
        private KnowledgeService $knowledge,
        private ConversationManager $manager,
        private IdentityResolver $resolver,
        private AiService $ai,
    ) {}

    /**
     * Primary entry point. Called by all channel controllers.
     * Returns the final reply string to send back to the user.
     */
    public function reply(ChannelMessage $inbound): string
    {
        // 1. Resolve identity and conversation
        ['identity' => $identity, 'conversation' => $conversation] = $this->resolver->resolve($inbound);

        // 2. Link authenticated web user if present
        if ($inbound->channel === 'web' && auth()->check() && !$identity->user_id) {
            $this->resolver->linkUser($identity, auth()->id());
            $identity = $identity->fresh();
        }

        $user = $identity->user_id ? User::find($identity->user_id) : null;
        $tier = $identity->getTier();

        // 3. Save incoming user message
        $this->manager->saveUserMessage($conversation, $inbound->content, $inbound->externalMsgId);

        // 4. Build system prompt with KB + pricing
        $systemPrompt = $this->knowledge->buildContext('ambassador', $tier, [
            '{{user_name}}'    => $user?->name ?? $identity->display_name ?? 'there',
            '{{user_role}}'    => $user?->getRoleNames()->first() ?? 'guest',
            '{{channel}}'      => $inbound->channel,
            '{{current_date}}' => now()->format('l, d F Y'),
        ]);

        // 5. Load conversation history
        $history = $this->manager->getHistory($conversation);

        // 6. Select tools based on tier
        $tools = $tier === 'guest' ? ToolSchemas::guest() : ToolSchemas::authenticated();

        // 7. LLM call with tool loop
        $dispatcher = new ToolDispatcher($identity, $conversation, $user, $this->resolver);
        $response   = $this->runWithTools($systemPrompt, $history, $tools, $dispatcher);

        // 8. Save assistant response
        $this->manager->saveAssistantMessage(
            $conversation,
            $response['content'],
            $response['tool_calls'] ?? null,
            $response['usage']['total_tokens'] ?? null,
        );

        // 9. Update conversation metadata
        $conversation->update(['last_message_at' => now()]);

        return $response['content'];
    }

    private function runWithTools(
        string $systemPrompt,
        array $history,
        array $tools,
        ToolDispatcher $dispatcher
    ): array {
        $messages   = $history;
        $toolCallLog = [];

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $result = $this->ai->chatWithTools($systemPrompt, $messages, $tools);

            // If no tool was called, we have our final response
            if (empty($result['tool_calls'])) {
                $result['tool_calls'] = $toolCallLog ?: null;
                return $result;
            }

            // Execute each requested tool call
            foreach ($result['tool_calls'] as $call) {
                $toolResult = $dispatcher->dispatch($call['function']['name'], $call['function']['arguments']);
                $toolCallLog[] = [
                    'name'   => $call['function']['name'],
                    'args'   => $call['function']['arguments'],
                    'result' => $toolResult,
                ];

                // Append tool result to message history for next iteration
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => null,
                    'tool_calls' => [$call],
                ];
                $messages[] = [
                    'role'        => 'tool',
                    'tool_call_id' => $call['id'],
                    'content'     => json_encode($toolResult),
                ];
            }
        }

        // Safety fallback if max iterations hit
        return [
            'content'    => 'I was unable to complete that action. Please try again or type "human" to reach a support agent.',
            'tool_calls' => $toolCallLog,
        ];
    }
}
```

---

### 1.9 — AiService Extension

Your existing `AiService` likely has a `chat()` method. Add `chatWithTools()` if it doesn't exist:

**File:** `app/Services/AiService.php` (add method only — do not rewrite the file)

```php
/**
 * Chat with tool calling support.
 * Returns array with 'content' (string) and optionally 'tool_calls' (array) and 'usage' (array).
 */
public function chatWithTools(string $systemPrompt, array $messages, array $tools): array
{
    $payload = [
        'model'    => config('ambassador.model', 'gpt-4o'),
        'messages' => array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        ),
        'tools'       => $tools,
        'tool_choice' => 'auto',
        'max_tokens'  => 1000,
    ];

    $response = $this->client->post('/chat/completions', $payload);
    $choice   = $response['choices'][0];

    if ($choice['finish_reason'] === 'tool_calls') {
        return [
            'content'    => '',
            'tool_calls' => $choice['message']['tool_calls'],
            'usage'      => $response['usage'],
        ];
    }

    return [
        'content' => $choice['message']['content'],
        'usage'   => $response['usage'],
    ];
}
```

---

### 1.10 — WebChatChannel & Controller

**File:** `app/Channels/WebChatChannel.php`

```php
<?php

namespace App\Channels;

use App\DTOs\ChannelMessage;
use Illuminate\Http\Request;

class WebChatChannel
{
    public function parse(Request $request): ChannelMessage
    {
        // For web chat, the external_id is the session ID.
        // This persists across page loads for the same browser.
        $sessionId = $request->session()->getId();

        return new ChannelMessage(
            channel:      'web',
            externalId:   $sessionId,
            content:      $request->input('message'),
            displayName:  auth()->user()?->name,
            phone:        null,
            email:        auth()->user()?->email,
            externalMsgId: null,
            meta:         [],
        );
    }
}
```

**File:** `app/Http/Controllers/Agent/WebChatController.php`

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Agents\AmbassadorAgent;
use App\Channels\WebChatChannel;
use App\Http\Controllers\Controller;
use App\Models\AgentChannelIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebChatController extends Controller
{
    public function __construct(
        private AmbassadorAgent $agent,
        private WebChatChannel $channel,
    ) {}

    public function message(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:2000']);

        $inbound = $this->channel->parse($request);
        $reply   = $this->agent->reply($inbound);

        return response()->json(['reply' => $reply]);
    }

    /**
     * Return the last N messages for the current session (used to restore chat on page reload).
     */
    public function history(Request $request): JsonResponse
    {
        $sessionId = $request->session()->getId();

        $identity = AgentChannelIdentity::where('channel', 'web')
            ->where('external_id', $sessionId)
            ->first();

        if (!$identity) {
            return response()->json(['messages' => []]);
        }

        $conversation = $identity->activeConversation;

        if (!$conversation) {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at')
            ->take(50)
            ->get(['role', 'content', 'created_at'])
            ->map(fn($m) => [
                'role'    => $m->role,
                'content' => $m->content,
                'time'    => $m->created_at->format('H:i'),
            ]);

        return response()->json(['messages' => $messages]);
    }
}
```

---

### 1.11 — AgentChatWidget React Component

**File:** `resources/js/Components/AgentChatWidget.jsx`

```jsx
import { useState, useEffect, useRef } from "react";
import axios from "axios";

export default function AgentChatWidget({ user }) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState("");
    const [loading, setLoading] = useState(false);
    const [initialized, setInitialized] = useState(false);
    const bottomRef = useRef(null);

    // Load history on first open
    useEffect(() => {
        if (open && !initialized) {
            axios.get("/agent/history").then((res) => {
                if (res.data.messages.length > 0) {
                    setMessages(res.data.messages);
                } else {
                    // Greeting message
                    setMessages([{
                        role: "assistant",
                        content: user
                            ? `Hi ${user.name}! How can I help you today?`
                            : "Hi there! Welcome to Maids.ng. Ask me anything about finding domestic staff.",
                        time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
                    }]);
                }
                setInitialized(true);
            });
        }
    }, [open]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: "smooth" });
    }, [messages]);

    const send = async () => {
        if (!input.trim() || loading) return;

        const userMessage = { role: "user", content: input, time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) };
        setMessages((m) => [...m, userMessage]);
        setInput("");
        setLoading(true);

        try {
            const res = await axios.post("/agent/chat", { message: userMessage.content });
            setMessages((m) => [...m, {
                role: "assistant",
                content: res.data.reply,
                time: new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
            }]);
        } catch {
            setMessages((m) => [...m, {
                role: "assistant",
                content: "Sorry, I'm having trouble right now. Please try again in a moment.",
                time: "",
            }]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
            {/* Chat Window */}
            {open && (
                <div className="w-80 sm:w-96 bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-gray-200"
                     style={{ height: "480px" }}>
                    {/* Header */}
                    <div className="bg-emerald-600 px-4 py-3 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm">M</div>
                            <div>
                                <p className="text-white text-sm font-semibold">Maids.ng Support</p>
                                <p className="text-emerald-200 text-xs">Online</p>
                            </div>
                        </div>
                        <button onClick={() => setOpen(false)} className="text-white/70 hover:text-white text-lg leading-none">×</button>
                    </div>

                    {/* Messages */}
                    <div className="flex-1 overflow-y-auto px-4 py-3 space-y-3 bg-gray-50">
                        {messages.map((msg, i) => (
                            <div key={i} className={`flex ${msg.role === "user" ? "justify-end" : "justify-start"}`}>
                                <div className={`max-w-[80%] px-3 py-2 rounded-2xl text-sm leading-relaxed
                                    ${msg.role === "user"
                                        ? "bg-emerald-600 text-white rounded-br-sm"
                                        : "bg-white text-gray-800 rounded-bl-sm shadow-sm border border-gray-100"}`}>
                                    <p style={{ whiteSpace: "pre-wrap" }}>{msg.content}</p>
                                    {msg.time && <p className={`text-xs mt-1 ${msg.role === "user" ? "text-emerald-200" : "text-gray-400"}`}>{msg.time}</p>}
                                </div>
                            </div>
                        ))}
                        {loading && (
                            <div className="flex justify-start">
                                <div className="bg-white border border-gray-100 shadow-sm px-3 py-2 rounded-2xl rounded-bl-sm">
                                    <div className="flex gap-1">
                                        {[0, 1, 2].map(i => (
                                            <div key={i} className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                                 style={{ animationDelay: `${i * 0.15}s` }} />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}
                        <div ref={bottomRef} />
                    </div>

                    {/* Input */}
                    <div className="px-3 py-3 border-t border-gray-100 bg-white flex gap-2">
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => e.key === "Enter" && !e.shiftKey && send()}
                            placeholder="Type your message..."
                            className="flex-1 text-sm border border-gray-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent"
                        />
                        <button onClick={send} disabled={loading}
                            className="bg-emerald-600 text-white rounded-xl px-3 py-2 text-sm font-medium hover:bg-emerald-700 disabled:opacity-50 transition-colors">
                            Send
                        </button>
                    </div>
                </div>
            )}

            {/* Toggle Button */}
            <button onClick={() => setOpen(!open)}
                className="w-14 h-14 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-105">
                {open ? (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                    </svg>
                ) : (
                    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                              d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                )}
            </button>
        </div>
    );
}
```

Include the widget in your main Inertia layout file (the one shared by all pages). Pass `agentUser` from `HandleInertiaRequests.php`:

**`app/Http/Middleware/HandleInertiaRequests.php`** — add to `share()` return array:

```php
'agentUser' => auth()->check() ? [
    'id'   => auth()->id(),
    'name' => auth()->user()->name,
] : null,
```

**In your root layout Blade/JSX**, include the widget:

```jsx
import AgentChatWidget from "@/Components/AgentChatWidget";
// In your layout's return:
<AgentChatWidget user={page.props.agentUser} />
```

---

### 1.12 — Routes (Phase 1 only)

**File:** `routes/agent.php`

```php
<?php

use App\Http\Controllers\Agent\WebChatController;
use Illuminate\Support\Facades\Route;

// Web chat (no auth required — works for guests and logged-in users)
Route::middleware('web')->group(function () {
    Route::post('/agent/chat',    [WebChatController::class, 'message']);
    Route::get('/agent/history',  [WebChatController::class, 'history']);
});
```

Add to `routes/web.php` at the bottom:

```php
require __DIR__ . '/agent.php';
```

Register service bindings in `AppServiceProvider::register()`:

```php
$this->app->singleton(\App\Agents\AmbassadorAgent::class);
$this->app->singleton(\App\Services\IdentityResolver::class);
$this->app->singleton(\App\Services\ConversationManager::class);
```

### Phase 1 — Definition of Done

- [ ] All 4 migrations run cleanly. `php artisan migrate:status` shows all as `Ran`.
- [ ] The chat widget appears on the homepage (guest) and dashboard (logged-in).
- [ ] Sending a message as a guest receives a contextually appropriate reply.
- [ ] The guest reply does NOT reveal any user data or answer account-specific questions.
- [ ] Sending a message as a logged-in employer receives a reply that can reference their account.
- [ ] The tool `get_account_summary` executes and the LLM incorporates the result into its reply.
- [ ] The tool `escalate_to_human` sets the conversation status to `escalated` in the DB.
- [ ] All messages are stored in `agent_messages` with the correct `role` values.
- [ ] `agent_channel_identities` has one row per unique session/user.
- [ ] Page reload restores the conversation history in the widget.
- [ ] No exceptions thrown for any message path. All errors caught and returned as friendly fallback text.

---

## Phase 2 — Email Channel (SMTP Send + Receive)

**Goal:** Users can email `support@maids.ng` and the Ambassador replies by email. The conversation is logged. Admin can see the email thread in the conversation view.

**Two-part problem:**
1. **Sending** — straightforward via Laravel Mail + SMTP
2. **Receiving** — requires one of three approaches (choose one based on your email provider)

---

### 2.1 — Receiving Inbound Email

#### Option A — Webhook-based (Recommended: Postmark, Mailgun, SendGrid, Brevo)

Most transactional email providers support **inbound email parsing**: you configure a catch-all address, and they POST a webhook to your server with the parsed email content (from, to, subject, body, attachments, headers).

**Use this if you are already using or can switch to Postmark or Brevo.** This is the cleanest approach — no polling, no IMAP, no cron jobs.

Setup:
1. Create an inbound address rule in your provider dashboard (e.g. `support@mg.maids.ng` or a forwarding alias)
2. Point the webhook to `https://maids.ng/webhooks/email/inbound`
3. Implement `EmailInboundController::handle()`

#### Option B — IMAP Polling (Any email provider including Gmail, Zoho, custom SMTP/IMAP)

If you have a standard `support@maids.ng` mailbox and cannot use a parsing provider, poll the inbox via IMAP on a scheduled job.

Install the PHP IMAP library:

```bash
composer require webklex/php-imap
```

This phase covers **both approaches**. Implement the one that matches your email provider.

---

### 2.2 — Config

**File:** `config/ambassador.php`

```php
<?php

return [
    'model'   => env('AMBASSADOR_MODEL', 'gpt-4o'),

    'email' => [
        // The address users email to reach support
        'inbound_address' => env('AGENT_EMAIL_INBOUND', 'support@maids.ng'),

        // The From address the agent uses when replying
        'from_address'    => env('AGENT_EMAIL_FROM', 'support@maids.ng'),
        'from_name'       => env('AGENT_EMAIL_FROM_NAME', 'Maids.ng Support'),

        // Webhook approach: secret token to validate inbound webhooks
        'webhook_secret'  => env('AGENT_EMAIL_WEBHOOK_SECRET'),

        // IMAP approach (Option B only)
        'imap' => [
            'host'     => env('AGENT_IMAP_HOST', 'imap.gmail.com'),
            'port'     => env('AGENT_IMAP_PORT', 993),
            'username' => env('AGENT_IMAP_USER'),
            'password' => env('AGENT_IMAP_PASSWORD'),
            'protocol' => 'imap',
            'encryption' => 'ssl',
        ],
    ],
];
```

**.env additions:**

```env
AGENT_EMAIL_INBOUND=support@maids.ng
AGENT_EMAIL_FROM=support@maids.ng
AGENT_EMAIL_FROM_NAME="Maids.ng Support"
AGENT_EMAIL_WEBHOOK_SECRET=your_random_secret_here

# If using IMAP polling:
AGENT_IMAP_HOST=imap.gmail.com
AGENT_IMAP_PORT=993
AGENT_IMAP_USER=support@maids.ng
AGENT_IMAP_PASSWORD=your_app_password_here
```

---

### 2.3 — EmailChannel Parser

**File:** `app/Channels/EmailChannel.php`

```php
<?php

namespace App\Channels;

use App\DTOs\ChannelMessage;
use Illuminate\Http\Request;

class EmailChannel
{
    /**
     * Parse a Postmark inbound webhook payload.
     * Adapt field names for your provider (Mailgun, SendGrid, Brevo all differ slightly).
     */
    public function parseWebhook(Request $request): ?ChannelMessage
    {
        $data = $request->all();

        $fromEmail = strtolower($this->extractEmail($data['From'] ?? ''));
        $body      = $this->extractBody($data);
        $messageId = $data['MessageID'] ?? $data['message-id'] ?? null;
        $subject   = $data['Subject'] ?? 'Support Request';

        // Extract threading info from headers
        $inReplyTo = $data['InReplyTo'] ?? $data['Headers']['In-Reply-To'] ?? null;
        $threadId  = $inReplyTo ?? $messageId;

        if (!$fromEmail || !$body) {
            return null;
        }

        // Ignore auto-replies and bounces
        if ($this->isAutoReply($data)) {
            return null;
        }

        return new ChannelMessage(
            channel:      'email',
            externalId:   $fromEmail,
            content:      $body,
            displayName:  $this->extractName($data['From'] ?? ''),
            phone:        null,
            email:        $fromEmail,
            externalMsgId: $messageId,
            meta: [
                'subject'   => $subject,
                'thread_id' => $threadId,
                'raw_from'  => $data['From'] ?? '',
            ],
        );
    }

    /**
     * Parse a single IMAP message object (webklex/php-imap Message).
     */
    public function parseImapMessage($imapMessage): ?ChannelMessage
    {
        $fromAddress = strtolower((string) $imapMessage->getFrom()[0]->mail ?? '');
        $body        = $this->cleanImapBody($imapMessage->getHTMLBody() ?: $imapMessage->getTextBody());
        $messageId   = (string) $imapMessage->getMessageId();
        $inReplyTo   = (string) ($imapMessage->getInReplyTo() ?? '');
        $subject     = (string) $imapMessage->getSubject();

        if (!$fromAddress || !$body) {
            return null;
        }

        return new ChannelMessage(
            channel:      'email',
            externalId:   $fromAddress,
            content:      $body,
            displayName:  (string) ($imapMessage->getFrom()[0]->personal ?? ''),
            phone:        null,
            email:        $fromAddress,
            externalMsgId: $messageId,
            meta: [
                'subject'   => $subject,
                'thread_id' => $inReplyTo ?: $messageId,
            ],
        );
    }

    private function extractEmail(string $from): string
    {
        // Handles "Display Name <email@domain.com>" and bare "email@domain.com"
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return strtolower(trim($matches[1]));
        }
        return strtolower(trim($from));
    }

    private function extractName(string $from): string
    {
        if (preg_match('/^([^<]+)</', $from, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    private function extractBody(array $data): string
    {
        // Prefer plain text body; strip quoted reply content
        $body = $data['TextBody'] ?? $data['body-plain'] ?? $data['text'] ?? '';

        // Strip quoted content (lines starting with ">")
        $lines = array_filter(
            explode("\n", $body),
            fn($line) => !str_starts_with(ltrim($line), '>')
        );

        // Strip common email footer markers
        $text = implode("\n", $lines);
        $text = preg_replace('/--+\s*\n.*/s', '', $text);

        return trim($text);
    }

    private function cleanImapBody(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function isAutoReply(array $data): bool
    {
        $headers = strtolower(json_encode($data['Headers'] ?? []));
        $subject = strtolower($data['Subject'] ?? '');

        return str_contains($headers, 'auto-submitted')
            || str_contains($headers, 'x-autoreply')
            || str_contains($subject, 'out of office')
            || str_contains($subject, 'automatic reply')
            || str_contains($subject, 'delivery failure');
    }
}
```

---

### 2.4 — Email Reply Mailable

**File:** `app/Mail/AgentReply.php`

```php
<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

class AgentReply extends Mailable
{
    public function __construct(
        private string $replyText,
        private string $toName,
        private string $subject,
        private ?string $inReplyToMessageId = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address(
                config('ambassador.email.from_address'),
                config('ambassador.email.from_name')
            ),
            subject: $this->subject,
        );
    }

    public function headers(): Headers
    {
        $headers = new Headers();

        // Thread emails correctly in mail clients
        if ($this->inReplyToMessageId) {
            $headers->addTextHeader('In-Reply-To', $this->inReplyToMessageId);
            $headers->addTextHeader('References', $this->inReplyToMessageId);
        }

        // Mark as automated so receiving servers handle it properly
        $headers->addTextHeader('Auto-Submitted', 'auto-replied');
        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

        return $headers;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agent-reply',
            with: [
                'replyText' => $this->replyText,
                'toName'    => $this->toName,
            ],
        );
    }
}
```

**File:** `resources/views/emails/agent-reply.blade.php`

```blade
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: -apple-system, sans-serif; color: #1a1a1a; line-height: 1.6; }
  .container { max-width: 600px; margin: 0 auto; padding: 24px; }
  .header { background: #059669; color: white; padding: 16px 24px; border-radius: 8px 8px 0 0; }
  .body { background: #ffffff; padding: 24px; border: 1px solid #e5e7eb; border-top: none; }
  .footer { padding: 16px 24px; font-size: 12px; color: #6b7280; text-align: center; }
</style>
</head>
<body>
  <div class="container">
    <div class="header">
      <strong>Maids.ng Support</strong>
    </div>
    <div class="body">
      <p>Hi {{ $toName }},</p>
      <div style="white-space: pre-wrap;">{{ $replyText }}</div>
      <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e7eb;">
      <p style="font-size: 13px; color: #6b7280;">
        Reply to this email and I'll get back to you shortly.<br>
        For urgent matters, you can also chat with us on <a href="{{ url('/') }}">maids.ng</a>.
      </p>
    </div>
    <div class="footer">
      Maids.ng — Nigeria's Trusted Domestic Staff Platform<br>
      <a href="{{ url('/unsubscribe') }}" style="color: #6b7280;">Unsubscribe</a>
    </div>
  </div>
</body>
</html>
```

---

### 2.5 — ChannelSender Service

A central service that sends replies back on the correct channel. Phase 1 doesn't need this (web replies are synchronous), but email requires it.

**File:** `app/Services/ChannelSender.php`

```php
<?php

namespace App\Services;

use App\Mail\AgentReply;
use App\Models\AgentConversation;
use Illuminate\Support\Facades\Mail;

class ChannelSender
{
    /**
     * Send the agent's reply back to the user on the correct channel.
     */
    public function send(AgentConversation $conversation, string $reply): void
    {
        match ($conversation->channel) {
            'email'     => $this->sendEmail($conversation, $reply),
            'whatsapp'  => $this->sendWhatsApp($conversation, $reply),
            'instagram' => $this->sendMetaDM($conversation, $reply),
            'facebook'  => $this->sendMetaDM($conversation, $reply),
            'web'       => null, // web replies are returned synchronously — no action needed
            default     => null,
        };
    }

    private function sendEmail(AgentConversation $conversation, string $reply): void
    {
        $identity = $conversation->identity;
        $toEmail  = $identity->email;
        $toName   = $identity->display_name ?? 'there';

        if (!$toEmail) {
            \Log::error("AmbassadorAgent: Cannot send email reply — no email on identity {$identity->id}");
            return;
        }

        $subject = $conversation->email_subject
            ? (str_starts_with($conversation->email_subject, 'Re:')
                ? $conversation->email_subject
                : 'Re: ' . $conversation->email_subject)
            : 'Re: Your Maids.ng Support Request';

        Mail::to($toEmail, $toName)->send(new AgentReply(
            replyText:          $reply,
            toName:             $toName,
            subject:            $subject,
            inReplyToMessageId: $conversation->email_thread_id,
        ));
    }

    // Phase 3 will implement these:
    private function sendWhatsApp(AgentConversation $conversation, string $reply): void { /* Phase 3 */ }
    private function sendMetaDM(AgentConversation $conversation, string $reply): void { /* Phase 4 */ }
}
```

---

### 2.6 — Email Inbound Controller (Webhook approach)

**File:** `app/Http/Controllers/Agent/EmailInboundController.php`

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Agents\AmbassadorAgent;
use App\Channels\EmailChannel;
use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Services\ChannelSender;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailInboundController extends Controller
{
    public function __construct(
        private AmbassadorAgent $agent,
        private EmailChannel $channel,
        private ChannelSender $sender,
    ) {}

    public function handle(Request $request): Response
    {
        // Validate webhook secret (provider-specific)
        // Postmark uses a header; Mailgun uses a hash; adapt as needed.
        if (!$this->validateWebhookSecret($request)) {
            return response('Unauthorized', 401);
        }

        $inbound = $this->channel->parseWebhook($request);

        if (!$inbound) {
            // Auto-reply or unparseable email — acknowledge and ignore
            return response('OK', 200);
        }

        // Get reply from agent
        $reply = $this->agent->reply($inbound);

        // Find the conversation that was just created/updated
        // We need to retrieve it to pass to the sender for subject/thread info
        $identity = \App\Models\AgentChannelIdentity::where('channel', 'email')
            ->where('external_id', $inbound->externalId)
            ->first();

        $conversation = $identity?->activeConversation
            ?? AgentConversation::where('channel_identity_id', $identity?->id)
               ->latest()
               ->first();

        if ($conversation) {
            $this->sender->send($conversation, $reply);
        }

        // Always return 200 to the webhook provider — retry logic can cause duplicate replies
        return response('OK', 200);
    }

    private function validateWebhookSecret(Request $request): bool
    {
        $secret = config('ambassador.email.webhook_secret');
        if (!$secret) {
            return true; // No secret configured — allow all (only do this in dev)
        }

        // Postmark sends secret as a custom header you define in their dashboard
        return $request->header('X-Webhook-Secret') === $secret;
    }
}
```

---

### 2.7 — IMAP Polling Job (Option B)

**File:** `app/Jobs/PollInboundEmail.php`

```php
<?php

namespace App\Jobs;

use App\Agents\AmbassadorAgent;
use App\Channels\EmailChannel;
use App\Models\AgentConversation;
use App\Services\ChannelSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Webklex\IMAP\Facades\Client;

class PollInboundEmail implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(AmbassadorAgent $agent, EmailChannel $channel, ChannelSender $sender): void
    {
        $config = config('ambassador.email.imap');

        $client = Client::make([
            'host'          => $config['host'],
            'port'          => $config['port'],
            'username'      => $config['username'],
            'password'      => $config['password'],
            'protocol'      => $config['protocol'],
            'encryption'    => $config['encryption'],
            'validate_cert' => true,
        ]);

        $client->connect();
        $folder = $client->getFolder('INBOX');

        // Fetch only unseen messages
        $messages = $folder->messages()->unseen()->get();

        foreach ($messages as $imapMessage) {
            try {
                $inbound = $channel->parseImapMessage($imapMessage);

                if ($inbound) {
                    $reply = $agent->reply($inbound);

                    $identity = \App\Models\AgentChannelIdentity::where('channel', 'email')
                        ->where('external_id', $inbound->externalId)
                        ->first();

                    $conversation = $identity?->activeConversation
                        ?? AgentConversation::where('channel_identity_id', $identity?->id)->latest()->first();

                    if ($conversation) {
                        $sender->send($conversation, $reply);
                    }
                }

                // Mark as read so we don't process it again
                $imapMessage->setFlag('Seen');

            } catch (\Exception $e) {
                \Log::error('Email poll error: ' . $e->getMessage(), [
                    'message_id' => (string) $imapMessage->getMessageId(),
                ]);
                // Don't mark as seen so it gets retried
            }
        }

        $client->disconnect();
    }
}
```

Schedule in `app/Console/Kernel.php` or `routes/console.php`:

```php
// Poll every 2 minutes
Schedule::job(new PollInboundEmail)->everyTwoMinutes();
```

---

### 2.8 — Email Routes (add to `routes/agent.php`)

```php
use App\Http\Controllers\Agent\EmailInboundController;

// Email inbound webhook (no CSRF — exclude in VerifyCsrfToken middleware)
Route::post('/webhooks/email/inbound', [EmailInboundController::class, 'handle'])
     ->middleware('api'); // or add to $except in VerifyCsrfToken
```

**`app/Http/Middleware/VerifyCsrfToken.php`** — add to `$except`:

```php
protected $except = [
    'webhooks/email/*',
    // (will also add WhatsApp and Meta in later phases)
];
```

### Phase 2 — Definition of Done

- [ ] Sending an email to `support@maids.ng` triggers the agent to process and reply (test with a real email).
- [ ] The reply arrives in the sender's inbox with correct `Re:` subject prefix.
- [ ] Reply threads correctly — mail client shows it as a continuation of the original thread.
- [ ] Auto-replies and bounce emails are silently ignored (no reply sent, no exception logged).
- [ ] The conversation and all messages are visible in `agent_conversations` / `agent_messages` tables.
- [ ] Duplicate emails (webhook retry, IMAP re-poll) do not create duplicate messages (`external_message_id` deduplication works).
- [ ] The IMAP job (if chosen) runs on schedule and processes unseen messages within the polling interval.
- [ ] Email from a known registered user (matched by email address) correctly retrieves their account context.
- [ ] Webhook endpoint returns HTTP 200 always — even for auto-replies — to prevent provider retries.

---

## Phase 3 — WhatsApp Channel (Meta Cloud API)

**Prerequisites:**
- An approved Meta Business Account
- A WhatsApp Business App created in Meta Developer Console
- A verified phone number added to the app
- A permanent System User token generated (not a temporary user token)
- Your server is live with valid HTTPS SSL certificate

---

### 3.1 — Meta WhatsApp Setup Steps

1. Go to `developers.facebook.com` → Create App → Business type
2. Add "WhatsApp" product to your app
3. Under WhatsApp → Getting Started, note your **Phone Number ID** and **WhatsApp Business Account ID**
4. Generate a permanent token: Business Settings → System Users → Create System User (Admin) → Generate Token → Select your app → Grant `whatsapp_business_messaging` and `whatsapp_business_management` permissions
5. Register your webhook URL: `https://maids.ng/webhooks/whatsapp`
6. Set your **Verify Token** (you define this — must match `META_WA_WEBHOOK_VERIFY_TOKEN` in `.env`)
7. Subscribe to the `messages` webhook field

**.env additions:**

```env
META_WA_PHONE_NUMBER_ID=your_phone_number_id
META_WA_ACCESS_TOKEN=your_permanent_system_user_token
META_WA_WEBHOOK_VERIFY_TOKEN=define_your_own_random_string_here
META_WA_API_VERSION=v19.0
```

---

### 3.2 — WhatsApp Channel Parser

**File:** `app/Channels/WhatsAppChannel.php`

```php
<?php

namespace App\Channels;

use App\DTOs\ChannelMessage;

class WhatsAppChannel
{
    /**
     * Parse a Meta Cloud API WhatsApp webhook payload.
     * Returns null for non-message events (status updates, read receipts, etc.)
     */
    public function parse(array $payload): ?ChannelMessage
    {
        $entry   = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0] ?? null;
        $value   = $changes['value'] ?? null;

        if (!$value || !isset($value['messages'])) {
            return null; // Status update or other non-message event
        }

        $message = $value['messages'][0];
        $contact = $value['contacts'][0] ?? null;

        // Only handle text messages for now
        // TODO: handle audio, image, document in a later iteration
        if ($message['type'] !== 'text') {
            return null;
        }

        $phone      = '+' . ltrim($message['from'], '+'); // Ensure E.164
        $text       = $message['text']['body'];
        $messageId  = $message['id'];
        $displayName = $contact['profile']['name'] ?? null;

        return new ChannelMessage(
            channel:      'whatsapp',
            externalId:   $phone,
            content:      $text,
            displayName:  $displayName,
            phone:        $phone,
            email:        null,
            externalMsgId: $messageId,
            meta: ['wa_display_name' => $displayName],
        );
    }
}
```

---

### 3.3 — WhatsApp Sender (add to ChannelSender)

**File:** `app/Services/ChannelSender.php` — update the `sendWhatsApp` method:

```php
private function sendWhatsApp(AgentConversation $conversation, string $reply): void
{
    $phone      = $conversation->identity->phone;
    $phoneNumId = config('services.meta.wa_phone_number_id');
    $token      = config('services.meta.wa_access_token');
    $version    = config('services.meta.wa_api_version', 'v19.0');

    if (!$phone || !$phoneNumId || !$token) {
        \Log::error('WhatsApp send failed: missing credentials or phone', [
            'conversation_id' => $conversation->id,
        ]);
        return;
    }

    // WhatsApp has a 4096 character limit per message
    // If reply is longer, split into chunks
    $chunks = $this->chunkMessage($reply, 4000);

    foreach ($chunks as $chunk) {
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => ['body' => $chunk, 'preview_url' => false],
            ]);

        if (!$response->successful()) {
            \Log::error('WhatsApp send error', [
                'status'   => $response->status(),
                'body'     => $response->body(),
                'phone'    => $phone,
            ]);
        }

        // Small delay between chunks to preserve order
        if (count($chunks) > 1) {
            usleep(200000); // 200ms
        }
    }
}

private function chunkMessage(string $text, int $limit): array
{
    if (strlen($text) <= $limit) {
        return [$text];
    }

    $chunks = [];
    $words  = explode(' ', $text);
    $chunk  = '';

    foreach ($words as $word) {
        if (strlen($chunk) + strlen($word) + 1 > $limit) {
            $chunks[] = trim($chunk);
            $chunk    = '';
        }
        $chunk .= ' ' . $word;
    }

    if (!empty(trim($chunk))) {
        $chunks[] = trim($chunk);
    }

    return $chunks;
}
```

Add to `config/services.php`:

```php
'meta' => [
    'wa_phone_number_id' => env('META_WA_PHONE_NUMBER_ID'),
    'wa_access_token'    => env('META_WA_ACCESS_TOKEN'),
    'wa_verify_token'    => env('META_WA_WEBHOOK_VERIFY_TOKEN'),
    'wa_api_version'     => env('META_WA_API_VERSION', 'v19.0'),
    'app_secret'         => env('META_APP_SECRET'),
    'page_access_token'  => env('META_PAGE_ACCESS_TOKEN'),
    'ig_verify_token'    => env('META_IG_WEBHOOK_VERIFY_TOKEN'),
],
```

---

### 3.4 — WhatsApp Webhook Controller

**File:** `app/Http/Controllers/Agent/WhatsAppWebhookController.php`

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Agents\AmbassadorAgent;
use App\Channels\WhatsAppChannel;
use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Services\ChannelSender;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private AmbassadorAgent $agent,
        private WhatsAppChannel $channel,
        private ChannelSender $sender,
    ) {}

    /**
     * GET — Meta calls this to verify the webhook endpoint.
     * Must respond within 10 seconds or Meta will retry.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.meta.wa_verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — Meta sends inbound messages here.
     * Must return 200 immediately — process asynchronously via job queue.
     */
    public function handle(Request $request): Response
    {
        // Validate the request came from Meta using the app secret
        if (!$this->validateSignature($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();

        // Dispatch to queue so we return 200 immediately
        // Meta will retry if it doesn't get a 200 within ~20 seconds
        \App\Jobs\ProcessAgentMessage::dispatch('whatsapp', $payload);

        return response('OK', 200);
    }

    private function validateSignature(Request $request): bool
    {
        $secret    = config('services.meta.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (!$secret || !$signature) {
            return app()->environment('local'); // Only allow unsigned in local dev
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
```

---

### 3.5 — ProcessAgentMessage Job

This job handles all async channel processing. All channels (WA, IG, FB) dispatch to this.

**File:** `app/Jobs/ProcessAgentMessage.php`

```php
<?php

namespace App\Jobs;

use App\Agents\AmbassadorAgent;
use App\Channels\{WhatsAppChannel, MetaDMChannel, EmailChannel};
use App\Models\AgentConversation;
use App\Services\ChannelSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessAgentMessage implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(
        private string $channel,
        private array $payload,
    ) {}

    public function handle(AmbassadorAgent $agent, ChannelSender $sender): void
    {
        $inbound = match ($this->channel) {
            'whatsapp'  => app(WhatsAppChannel::class)->parse($this->payload),
            'instagram' => app(MetaDMChannel::class)->parseInstagram($this->payload),
            'facebook'  => app(MetaDMChannel::class)->parseFacebook($this->payload),
            default     => null,
        };

        if (!$inbound) {
            return; // Non-message event — nothing to process
        }

        $reply = $agent->reply($inbound);

        // Find the conversation that was just updated
        $identity = \App\Models\AgentChannelIdentity::where('channel', $this->channel)
            ->where('external_id', $inbound->externalId)
            ->first();

        $conversation = $identity?->activeConversation
            ?? AgentConversation::where('channel_identity_id', $identity?->id)->latest()->first();

        if ($conversation) {
            $sender->send($conversation, $reply);
        }
    }
}
```

---

### 3.6 — WhatsApp Routes (add to `routes/agent.php`)

```php
use App\Http\Controllers\Agent\WhatsAppWebhookController;

Route::get('/webhooks/whatsapp',  [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
     ->middleware('api');
```

Add to `VerifyCsrfToken::$except`:

```php
'webhooks/whatsapp',
```

### Phase 3 — Definition of Done

- [ ] Sending a WhatsApp message to your business number triggers a reply from the agent.
- [ ] The conversation is logged in `agent_conversations` with `channel = 'whatsapp'`.
- [ ] The sender's phone number is stored as `external_id` in E.164 format.
- [ ] The webhook verify GET request returns the `hub_challenge` correctly.
- [ ] Signature validation rejects requests not from Meta.
- [ ] Status update events (message delivered, read) are silently ignored — no processing, no error.
- [ ] Long replies (>4000 chars) are split into multiple messages sent in sequence.
- [ ] A registered user messaging from a WA number that matches their account phone is recognised (after OTP — Phase 5).
- [ ] The queue worker processes `whatsapp` jobs. Run `php artisan queue:work` and confirm jobs complete.

---

## Phase 4 — Instagram & Facebook DM (Meta Graph API)

**Prerequisites:**
- Your Facebook Page connected to Instagram Business account
- Both connected to the same Meta App used for WhatsApp
- `pages_messaging` and `instagram_manage_messages` permissions approved on the App

**.env additions:**

```env
META_PAGE_ACCESS_TOKEN=your_page_permanent_access_token
META_IG_WEBHOOK_VERIFY_TOKEN=define_your_own_random_string
META_APP_SECRET=your_meta_app_secret
```

---

### 4.1 — MetaDMChannel Parser

**File:** `app/Channels/MetaDMChannel.php`

```php
<?php

namespace App\Channels;

use App\DTOs\ChannelMessage;

class MetaDMChannel
{
    /**
     * Parse Instagram DM webhook payload.
     * The payload object field will be 'instagram'.
     */
    public function parseInstagram(array $payload): ?ChannelMessage
    {
        return $this->parseDM($payload, 'instagram');
    }

    /**
     * Parse Facebook Messenger webhook payload.
     * The payload object field will be 'page'.
     */
    public function parseFacebook(array $payload): ?ChannelMessage
    {
        return $this->parseDM($payload, 'facebook');
    }

    private function parseDM(array $payload, string $channel): ?ChannelMessage
    {
        $entry     = $payload['entry'][0] ?? null;
        $messaging = $entry['messaging'][0] ?? null;

        if (!$messaging || !isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];

        // Ignore echo events (messages sent BY the page, not to it)
        if ($message['is_echo'] ?? false) {
            return null;
        }

        // Only handle text messages
        if (!isset($message['text'])) {
            return null;
        }

        $psid      = $messaging['sender']['id'];
        $text      = $message['text'];
        $messageId = $message['mid'];

        return new ChannelMessage(
            channel:      $channel,
            externalId:   $psid,
            content:      $text,
            displayName:  null, // Fetched separately if needed
            phone:        null,
            email:        null,
            externalMsgId: $messageId,
            meta: ['psid' => $psid],
        );
    }
}
```

---

### 4.2 — Meta DM Sender (update ChannelSender)

**File:** `app/Services/ChannelSender.php` — update `sendMetaDM`:

```php
private function sendMetaDM(AgentConversation $conversation, string $reply): void
{
    $psid    = $conversation->identity->external_id;
    $token   = config('services.meta.page_access_token');
    $version = config('services.meta.wa_api_version', 'v19.0');

    if (!$psid || !$token) {
        \Log::error('Meta DM send failed: missing PSID or token', [
            'conversation_id' => $conversation->id,
        ]);
        return;
    }

    // Meta Messenger has a 2000 character limit
    $chunks = $this->chunkMessage($reply, 1900);

    foreach ($chunks as $chunk) {
        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->post("https://graph.facebook.com/{$version}/me/messages", [
                'recipient' => ['id' => $psid],
                'message'   => ['text' => $chunk],
                'messaging_type' => 'RESPONSE',
            ]);

        if (!$response->successful()) {
            \Log::error('Meta DM send error', [
                'channel' => $conversation->channel,
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
        }

        if (count($chunks) > 1) {
            usleep(200000);
        }
    }
}
```

---

### 4.3 — Meta DM Webhook Controller

IG and FB use the same webhook URL — distinguished by the `object` field in the payload.

**File:** `app/Http/Controllers/Agent/MetaWebhookController.php`

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAgentMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MetaWebhookController extends Controller
{
    /**
     * GET — verify both IG and FB webhooks with one endpoint.
     * Register the same URL in Meta App for both Instagram and Facebook Page.
     * Use different verify tokens for each in the Meta dashboard, OR use one token for both.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $validTokens = [
            config('services.meta.ig_verify_token'),
            config('services.meta.wa_verify_token'), // reuse if same
        ];

        if ($mode === 'subscribe' && in_array($token, array_filter($validTokens))) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * POST — handles both IG and FB DM events.
     */
    public function handle(Request $request): Response
    {
        if (!$this->validateSignature($request)) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $object  = $payload['object'] ?? '';

        $channel = match ($object) {
            'instagram' => 'instagram',
            'page'      => 'facebook',
            default     => null,
        };

        if ($channel) {
            ProcessAgentMessage::dispatch($channel, $payload);
        }

        return response('OK', 200);
    }

    private function validateSignature(Request $request): bool
    {
        $secret    = config('services.meta.app_secret');
        $signature = $request->header('X-Hub-Signature-256');

        if (!$secret || !$signature) {
            return app()->environment('local');
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }
}
```

---

### 4.4 — Meta DM Routes (add to `routes/agent.php`)

```php
use App\Http\Controllers\Agent\MetaWebhookController;

Route::get('/webhooks/meta',  [MetaWebhookController::class, 'verify']);
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->middleware('api');
```

Add to `VerifyCsrfToken::$except`:

```php
'webhooks/meta',
```

Register webhook in Meta App Dashboard:
- **URL:** `https://maids.ng/webhooks/meta`
- **For Instagram:** Products → Instagram → Webhooks → Subscribe to `messages`
- **For Facebook:** Products → Messenger → Webhooks → Subscribe to `messages`

### Phase 4 — Definition of Done

- [ ] Sending a DM to your Instagram Business account triggers a reply from the agent.
- [ ] Sending a DM to your Facebook Page triggers a reply from the agent.
- [ ] Both are logged as separate channel conversations (`instagram`, `facebook`).
- [ ] Echo events (page's own messages) are silently ignored.
- [ ] Signature validation rejects non-Meta requests.
- [ ] A single webhook URL handles both IG and FB correctly based on the `object` field.

---

## Phase 5 — Identity Resolution, OTP Auth & Tool Execution

**Goal:** Users on external channels (WA, IG, FB) can authenticate themselves, unlocking full account-aware support. Tool executions (create request, get status) are fully operational.

---

### 5.1 — OTP Flow Design

The agent handles this conversationally — no form, no redirect. The prompt template for the `lead` tier instructs the agent:

> *When a user on an external channel asks about their account, ask for their phone number. If provided, call `send_otp`. When they provide the code, call `verify_otp`. If verified, inform them they now have full access.*

The `AuthTools` class (built in Phase 1) handles the actual OTP logic. Ensure `SmsService` is wired to your Termii credentials:

**File:** `config/services.php` — add:

```php
'termii' => [
    'api_key'    => env('TERMII_API_KEY'),
    'sender_id'  => env('TERMII_SENDER_ID', 'Maids.ng'),
    'base_url'   => 'https://api.ng.termii.com/api',
],
```

**File:** `app/Services/SmsService.php` — ensure a `send(string $phone, string $message): bool` method exists using Termii's Send Message endpoint.

---

### 5.2 — Web Channel Auto-Link on Login

When a logged-in user opens the chat widget, their web session identity should be linked to their user account automatically. This happens in `AmbassadorAgent::reply()` (already implemented in Phase 1).

To ensure the session persists after login, add this to your login success handler:

```php
// After Auth::login($user):
// The session ID doesn't change in Laravel after login (session is regenerated).
// AmbassadorAgent::reply() handles the linkage on first message after login.
// No additional code needed here.
```

---

### 5.3 — Upgrading Tier Mid-Conversation

After `verify_otp` succeeds and `IdentityResolver::linkUser()` is called, the identity's `user_id` is set and `is_verified = true`. On the **next message**, `identity->getTier()` returns `'authenticated'`, so `KnowledgeService::buildContext()` returns the authenticated-tier prompt automatically. No session restart needed.

---

### 5.4 — Lead Intent Tracking

Update `AgentLead` intent when the user expresses what they want. The agent calls `collect_lead_info` with `intent_notes`. After collecting sufficient info, update the lead status:

In `UserTools::collectLeadInfo()`, if the lead has `name`, `phone`, and `intent_notes` populated, auto-promote to `warm`:

```php
if ($lead->name && $lead->phone && isset($args['intent_notes'])) {
    $lead->update(['status' => 'warm']);
}
```

---

### Phase 5 — Definition of Done

- [ ] A WhatsApp user who says "I want to check my account" is guided through the OTP flow.
- [ ] After entering the correct OTP, subsequent messages in the same conversation have full account access.
- [ ] An incorrect OTP returns a friendly error and allows retry.
- [ ] An expired OTP (>10 min) is rejected with a message to request a new code.
- [ ] `agent_channel_identities.user_id` is populated and `is_verified = true` after successful OTP.
- [ ] `agent_leads.status` updates to `registered` after OTP verification.
- [ ] The `create_maid_request` tool creates a real record in the `matching_preferences` table.
- [ ] The `get_assignment_status` tool returns real assignment data from the DB.
- [ ] The `escalate_to_human` tool sets `conversation.status = 'escalated'`.
- [ ] A guest on the web widget cannot call authenticated-only tools — they receive a friendly refusal.

---

## Phase 6 — Admin Conversation Dashboard

**Goal:** Admins can view all conversations across all channels, filter by status/channel/date, see full message threads, manually reply, escalate, and resolve.

---

### 6.1 — Admin Controller

**File:** `app/Http/Controllers/Agent/AdminConversationController.php`

```php
<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentConversation;
use App\Models\AgentMessage;
use App\Services\ChannelSender;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminConversationController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $conversations = AgentConversation::with([
                'identity:id,channel,external_id,display_name,phone,email,user_id,is_verified',
                'identity.user:id,name,email',
            ])
            ->when($request->channel, fn($q, $c) => $q->where('channel', $c))
            ->when($request->status,  fn($q, $s) => $q->where('status', $s))
            ->when($request->search,  fn($q, $s) => $q->whereHas('identity', fn($qi) =>
                $qi->where('display_name', 'like', "%{$s}%")
                   ->orWhere('phone', 'like', "%{$s}%")
                   ->orWhere('email', 'like', "%{$s}%")
            ))
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn($c) => [
                'id'             => $c->id,
                'channel'        => $c->channel,
                'status'         => $c->status,
                'identity'       => [
                    'display_name' => $c->identity->display_name,
                    'phone'        => $c->identity->phone,
                    'email'        => $c->identity->email,
                    'is_verified'  => $c->identity->is_verified,
                    'user'         => $c->identity->user
                        ? ['id' => $c->identity->user->id, 'name' => $c->identity->user->name]
                        : null,
                ],
                'intent_summary' => $c->intent_summary,
                'last_message_at' => $c->last_message_at?->diffForHumans(),
                'unread_count'   => $c->messages()->where('role', 'user')->where('admin_read', false)->count(),
            ]);

        return Inertia::render('Admin/Agent/Conversations/Index', [
            'conversations' => $conversations,
            'filters'       => $request->only(['channel', 'status', 'search']),
            'channels'      => ['web', 'email', 'whatsapp', 'instagram', 'facebook'],
            'statuses'      => ['open', 'resolved', 'escalated', 'converted', 'spam'],
        ]);
    }

    public function show(int $id): \Inertia\Response
    {
        $conversation = AgentConversation::with([
                'identity.user',
                'identity.lead',
                'assignedAdmin:id,name',
                'messages' => fn($q) => $q->orderBy('created_at'),
            ])
            ->findOrFail($id);

        // Mark all user messages as read
        $conversation->messages()
            ->where('role', 'user')
            ->where('admin_read', false)
            ->update(['admin_read' => true]);

        return Inertia::render('Admin/Agent/Conversations/Show', [
            'conversation' => $conversation,
        ]);
    }

    public function reply(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['message' => 'required|string|max:4000']);

        $conversation = AgentConversation::findOrFail($id);

        // Save the admin reply as a message
        AgentMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => 'admin',
            'content'         => $request->message,
            'created_at'      => now(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        // Send it to the user on their channel
        app(ChannelSender::class)->send($conversation, $request->message);

        return back()->with('success', 'Reply sent.');
    }

    public function updateStatus(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:open,resolved,escalated,converted,spam',
            'note'   => 'nullable|string|max:1000',
        ]);

        AgentConversation::findOrFail($id)->update([
            'status'     => $request->status,
            'admin_note' => $request->note,
            'assigned_to' => $request->status === 'escalated' ? auth()->id() : null,
        ]);

        return back()->with('success', 'Status updated.');
    }
}
```

---

### 6.2 — Admin Routes (add to `routes/agent.php`)

```php
use App\Http\Controllers\Agent\AdminConversationController;

Route::middleware(['auth', 'role:admin'])->prefix('admin/agent')->name('admin.agent.')->group(function () {
    Route::get('/conversations',            [AdminConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{id}',       [AdminConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{id}/reply',[AdminConversationController::class, 'reply'])->name('conversations.reply');
    Route::patch('/conversations/{id}/status', [AdminConversationController::class, 'updateStatus'])->name('conversations.status');
});
```

---

### 6.3 — Admin UI Pages

**`resources/js/Pages/Admin/Agent/Conversations/Index.jsx`**

Filterable table with columns:
- Channel icon (web/email/WA/IG/FB coloured badges)
- Identity (display name, phone or email)
- User link (if authenticated — links to user admin profile)
- Intent Summary (truncated to 80 chars)
- Status badge (colour-coded)
- Unread count badge
- Last activity (relative time)
- Click row → `show` page

**`resources/js/Pages/Admin/Agent/Conversations/Show.jsx`**

Two-panel layout:
- **Left panel:** Full message thread — each message shows role, content, and timestamp. User messages are right-aligned, assistant/admin left-aligned, tool messages shown as collapsible JSON blocks.
- **Right panel:** Identity info (channel, phone, email, is_verified, link to user account), conversation metadata (status dropdown, admin note field, assign-to-me button), lead info if applicable.
- **Bottom bar:** Text area for admin reply + Send button. Status update controls.

### Phase 6 — Definition of Done

- [ ] `/admin/agent/conversations` loads paginated conversations with correct filters.
- [ ] Filtering by channel shows only conversations from that channel.
- [ ] Filtering by status shows only conversations with that status.
- [ ] Search by name/phone/email returns correct results.
- [ ] Clicking a conversation shows the full message thread.
- [ ] Tool call messages are visible as collapsible JSON blocks (not shown to user, visible to admin).
- [ ] Admin can type a reply, click Send — the user receives it on their channel.
- [ ] Admin can change conversation status to resolved/escalated.
- [ ] Unread message count decrements to 0 after admin opens the conversation.
- [ ] Escalated conversations show the assigning admin's name.
- [ ] All admin pages are protected by `auth + role:admin`. 403 returned for non-admins.

---

## Global Definition of Done

The entire Ambassador Agent implementation is **complete** when ALL of the following are true:

### Infrastructure
1. All 4 new database tables exist with correct schema and indexes.
2. No existing tables have been modified.
3. `php artisan migrate:fresh --seed` runs cleanly from scratch.
4. Queue worker processes jobs from all channels without errors: `php artisan queue:work`.
5. No hardcoded API keys, tokens, or secrets exist in any PHP or JS file — all in `.env`.

### Web Channel
6. The chat widget appears on both the guest homepage and the authenticated dashboard.
7. Guest conversations are scoped — no account data is leaked to unauthenticated users.
8. Authenticated user conversations include account-aware responses.
9. Chat history restores on page reload within the same browser session.
10. The widget is mobile-responsive and works on screens 320px wide and above.

### Email Channel
11. Emailing `support@maids.ng` results in an agent reply within 3 minutes (webhook) or within the poll interval + processing time (IMAP).
12. Replies thread correctly in Gmail, Apple Mail, and Outlook.
13. Auto-replies and bounces are silently dropped.
14. No duplicate replies are ever sent for the same inbound email.

### WhatsApp Channel
15. A WhatsApp message to the business number receives a reply within 30 seconds.
16. Messages longer than 4000 characters are split and sent in order.
17. Status update events do not trigger any processing or errors.
18. The webhook verify endpoint correctly echoes the `hub_challenge`.

### Instagram & Facebook DM
19. A DM to the Instagram Business account receives a reply within 30 seconds.
20. A DM to the Facebook Page receives a reply within 30 seconds.
21. Echo events (page's own messages) are ignored.
22. Both channels share one webhook URL and are distinguished correctly.

### Identity & Auth
23. Every unique sender has exactly one `agent_channel_identity` row per channel.
24. A guest who creates an account during a web chat session has their pre-signup conversation linked to their new `user_id`.
25. A WhatsApp user who completes OTP has `is_verified = true` and `user_id` set on their identity.
26. An incorrect OTP never links the identity to a user.
27. OTPs expire after 10 minutes.

### Tools
28. `collect_lead_info` saves data to both `agent_channel_identities` and `agent_leads`.
29. `create_maid_request` creates a real DB record accessible from the employer dashboard.
30. `get_assignment_status` returns real data from the assignments table.
31. `escalate_to_human` sets the conversation to `escalated` status in the DB.
32. `send_otp` + `verify_otp` complete the full auth flow end-to-end.
33. No tool can be called by a guest that requires authentication — the dispatcher returns a friendly refusal.

### Admin Dashboard
34. All conversations from all channels are visible in the admin dashboard.
35. The admin can filter, search, read threads, reply, and update status.
36. Admin replies are delivered to the user on their original channel.
37. Tool call logs are visible to admin as collapsible JSON.
38. All admin routes return 403 for non-admin authenticated users.

### Quality
39. No PHP exceptions are logged during any normal message flow on any channel.
40. All channel webhook endpoints return HTTP 200 within 3 seconds regardless of processing time (queue-based).
41. The LLM never reveals a user's personal data to another user — confirmed by manual testing across multiple test accounts.
42. `php artisan test` passes with no new failures introduced by this implementation.

---

*End of Ambassador Agent Implementation Guide.*  
*Next document: Deployment & Production Checklist.*
