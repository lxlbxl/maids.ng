<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Paperclip issue per WhatsApp contact, for the life of the relationship.
 *
 * Before this, issue identity was guessed from the title, and two writers
 * guessed differently: the WACRM bridge created "[MAIDS] WA: <conversation_id>"
 * and looked it up with `title LIKE %conversation_id%`, while agents created
 * "WA: <name> — <phone>". Neither could find the other's issue, so the same
 * person could end up with several threads (2347036268000 has three), and 45 of
 * 115 WhatsApp issues carry no phone number at all — they cannot be reconciled
 * to a person by any means.
 *
 * Outbound was worse: nothing in wa-send, wa-send-template, wa-guard or
 * maid-group-invite ever wrote to an issue, so every message we sent was
 * invisible in the thread. Reading an issue told you only half the conversation.
 *
 * This table is the authority: a normalised WhatsApp id maps to exactly one
 * issue, and both directions of traffic append to it. It lives in the maids
 * database rather than as an index on Paperclip's own issues table, so a
 * Paperclip migration can never collide with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contact_issues', function (Blueprint $table) {
            $table->id();

            // Normalised to full international form without '+' (2348012345678),
            // so 0801…, +234801… and 234801… all resolve to one row.
            $table->string('wa_id', 32)->unique();

            // Paperclip issue uuid, plus the human identifier (MAI-1234) so logs
            // and agent chatter can reference the thread without a second query.
            $table->uuid('issue_id');
            $table->string('issue_identifier', 32)->nullable();

            // Linked app user when we know who this is. Nullable: a helper often
            // messages us before she has an account.
            $table->unsignedBigInteger('user_id')->nullable();

            // 'adopted' when claimed from a pre-existing issue, 'created' when we
            // opened it. Useful for auditing the backfill.
            $table->string('origin', 16)->default('created');

            $table->string('display_name')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->timestamps();

            $table->index('issue_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_contact_issues');
    }
};
