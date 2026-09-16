<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * Resolves a WhatsApp contact to the one Paperclip issue that represents them,
 * and appends both directions of traffic to it.
 *
 * Every reach-out — inbound reply, outbound nudge, group job ack, NIN reminder,
 * campaign blast — lands on the same thread, so opening an issue shows the whole
 * relationship rather than one half of one episode.
 */
class WaContactIssueService
{
    private const PAPERCLIP_DB = 'paperclip';
    private const COMPANY_ID   = 'ada987c3-793e-4e0c-92fd-db3acc1a2f74';

    /** Agent the thread is assigned to when we open it (Peace). */
    private const DEFAULT_AGENT_ID = '369293e5-88da-4469-a44e-4397624aa3d5';

    /**
     * Canonical WhatsApp id: digits only, in full international form.
     *
     * Nigerian numbers arrive as 08012345678, +2348012345678, 2348012345678 and
     * 8012345678 depending on who typed them. They are one person, and a map
     * keyed on the raw string would give them one thread each.
     */
    public function normalize(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';

        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '234')) {
            return $d;
        }
        if (str_starts_with($d, '0') && strlen($d) === 11) {
            return '234' . substr($d, 1);          // 08012345678
        }
        if (strlen($d) === 10 && !str_starts_with($d, '0')) {
            return '234' . $d;                      // 8012345678
        }

        return $d;   // already international, or not Nigerian — leave it alone
    }

    /**
     * The issue for this contact, creating or adopting one if needed.
     *
     * Resolution order matters. The map is authoritative; only when it has
     * nothing do we look for a pre-existing Paperclip issue carrying this number
     * and adopt it, so turning this on does not orphan years of history.
     *
     * @return array{issue_id:string, identifier:?string, wa_id:string, created:bool}
     */
    public function resolve(string $phone, ?string $name = null, ?int $userId = null): array
    {
        $waId = $this->normalize($phone);
        if ($waId === '') {
            throw new \InvalidArgumentException('Cannot resolve an issue for an empty phone number.');
        }

        $row = DB::table('wa_contact_issues')->where('wa_id', $waId)->first();
        if ($row && $this->issueExists($row->issue_id)) {
            $this->touchMeta($waId, $name, $userId);
            return ['issue_id' => $row->issue_id, 'identifier' => $row->issue_identifier,
                    'wa_id' => $waId, 'created' => false];
        }

        // Map is stale or empty — is there already a thread for this number?
        if ($adopted = $this->findExistingIssueForNumber($waId)) {
            $this->remember($waId, $adopted->id, $adopted->identifier, $name, $userId, 'adopted');
            Log::info('WA issue adopted for contact', ['wa_id' => $waId, 'issue' => $adopted->identifier]);
            return ['issue_id' => $adopted->id, 'identifier' => $adopted->identifier,
                    'wa_id' => $waId, 'created' => false];
        }

        $created = $this->createIssue($waId, $name, $userId);
        $this->remember($waId, $created['id'], $created['identifier'], $name, $userId, 'created');
        Log::info('WA issue opened for contact', ['wa_id' => $waId, 'issue' => $created['identifier']]);

        return ['issue_id' => $created['id'], 'identifier' => $created['identifier'],
                'wa_id' => $waId, 'created' => true];
    }

    /** Append an inbound message to the contact's thread. */
    public function logInbound(string $phone, string $text, array $meta = []): array
    {
        $issue = $this->resolve($phone, $meta['name'] ?? null, $meta['user_id'] ?? null);
        $this->reopenIfClosed($issue['issue_id']);
        $this->comment($issue['issue_id'], $this->renderBody('in', $text, $meta));
        DB::table('wa_contact_issues')->where('wa_id', $issue['wa_id'])
            ->update(['last_inbound_at' => now(), 'updated_at' => now()]);

        return $issue;
    }

    /**
     * Append an outbound message to the contact's thread.
     *
     * Deliberately does not reopen a closed issue: a routine nudge should not
     * drag a settled thread back into the queue. An inbound reply will.
     */
    public function logOutbound(string $phone, string $text, array $meta = []): array
    {
        $issue = $this->resolve($phone, $meta['name'] ?? null, $meta['user_id'] ?? null);
        $this->comment($issue['issue_id'], $this->renderBody('out', $text, $meta));
        DB::table('wa_contact_issues')->where('wa_id', $issue['wa_id'])
            ->update(['last_outbound_at' => now(), 'updated_at' => now()]);

        return $issue;
    }

    // ---------------------------------------------------------------- internals

    private function renderBody(string $direction, string $text, array $meta): string
    {
        $isIn  = $direction === 'in';
        $label = $isIn ? 'WhatsApp inbound' : 'WhatsApp outbound';
        $arrow = $isIn ? '←' : '→';

        $body = "---\n**{$arrow} {$label}**\n";
        foreach (['wa_id', 'name', 'purpose', 'sent_by', 'template', 'instance', 'whatsapp_message_id', 'content_type'] as $k) {
            if (!empty($meta[$k])) {
                $body .= "{$k}: `{$meta[$k]}`\n";
            }
        }
        $body .= "\n> " . str_replace("\n", "\n> ", trim($text)) . "\n";

        return $body;
    }

    private function issueExists(string $issueId): bool
    {
        return DB::connection(self::PAPERCLIP_DB)->table('issues')->where('id', $issueId)->exists();
    }

    /**
     * Look for a thread already carrying this number.
     *
     * Matches the last 10 digits so the stored format does not matter — titles
     * in the wild carry 2348…, +2348… and 08… for the same person. Prefers the
     * most recently touched, which is the thread a human would consider current.
     */
    private function findExistingIssueForNumber(string $waId): ?object
    {
        $tail = substr($waId, -10);
        if (strlen($tail) < 9) {
            return null;
        }

        // Only ever adopt a thread that IS this contact's thread.
        //
        // Matching the number anywhere in the description is far too loose: bulk
        // issues tabulate dozens of numbers — a reactivation wave (MAI-1377)
        // lists forty — and every one of those contacts then adopts the campaign
        // report as their personal thread. Sixty contacts were mis-mapped across
        // four bulk issues that way before this was tightened.
        //
        // So: the number must sit where a per-contact thread puts it — at the
        // end of the title after the em dash, in "WA: <tag>-<number>", or in
        // origin_id, which only this service writes.
        $candidates = DB::connection(self::PAPERCLIP_DB)->table('issues')
            ->where('company_id', self::COMPANY_ID)
            ->whereNull('hidden_at')
            ->where(function ($q) use ($tail) {
                $q->where('origin_id', 'like', "%{$tail}")
                  ->orWhere('title', 'like', "%{$tail}");
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'identifier', 'title', 'origin_id']);

        foreach ($candidates as $c) {
            // origin_id is ours and exact — trust it outright.
            if ($c->origin_id && str_ends_with(preg_replace('/\D+/', '', $c->origin_id), $tail)) {
                return $c;
            }
            // "WA: Alero — 2348077884894" / "[MAIDS] WA: Maids-2348032200153"
            if (preg_match('/(?:—|-)\s*\+?\d*' . preg_quote($tail, '/') . '\s*$/u', $c->title)) {
                return $c;
            }
        }

        return null;
    }

    /** @return array{id:string, identifier:?string} */
    private function createIssue(string $waId, ?string $name, ?int $userId): array
    {
        $id    = (string) Uuid::uuid4();
        $label = $name ?: $this->lookupName($waId, $userId) ?: 'Unknown';

        DB::connection(self::PAPERCLIP_DB)->table('issues')->insert([
            'id'          => $id,
            'company_id'  => self::COMPANY_ID,
            'title'       => "WA: {$label} — {$waId}",
            'description' => json_encode([
                'kind'    => 'whatsapp_contact',
                'wa_id'   => $waId,
                'user_id' => $userId,
                'name'    => $label,
                'note'    => 'Canonical thread for this WhatsApp contact. All inbound and outbound messages append here.',
            ]),
            'status'             => 'todo',
            'priority'           => 'medium',
            'assignee_agent_id'  => self::DEFAULT_AGENT_ID,
            // origin_id carries the number so the thread is findable from
            // Paperclip alone if this mapping table is ever lost.
            'origin_kind'        => 'whatsapp_contact',
            'origin_id'          => $waId,
            'origin_fingerprint' => 'default',
            'request_depth'      => 0,
            'monitor_attempt_count' => 0,
            'work_mode'          => 'default',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $identifier = DB::connection(self::PAPERCLIP_DB)->table('issues')->where('id', $id)->value('identifier');

        return ['id' => $id, 'identifier' => $identifier];
    }

    private function comment(string $issueId, string $body): void
    {
        $companyId = DB::connection(self::PAPERCLIP_DB)->table('issues')->where('id', $issueId)->value('company_id');
        if (!$companyId) {
            Log::warning('WA thread: comment skipped, issue vanished', ['issue_id' => $issueId]);
            return;
        }

        DB::connection(self::PAPERCLIP_DB)->table('issue_comments')->insert([
            'id'         => (string) Uuid::uuid4(),
            'issue_id'   => $issueId,
            'company_id' => $companyId,
            'body'       => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function reopenIfClosed(string $issueId): void
    {
        DB::connection(self::PAPERCLIP_DB)->table('issues')
            ->where('id', $issueId)
            ->whereIn('status', ['done', 'cancelled'])
            ->update(['status' => 'todo', 'updated_at' => now()]);
    }

    private function remember(string $waId, string $issueId, ?string $identifier, ?string $name, ?int $userId, string $origin): void
    {
        DB::table('wa_contact_issues')->updateOrInsert(
            ['wa_id' => $waId],
            [
                'issue_id'         => $issueId,
                'issue_identifier' => $identifier,
                'user_id'          => $userId ?: $this->lookupUserId($waId),
                'display_name'     => $name,
                'origin'           => $origin,
                'updated_at'       => now(),
                'created_at'       => now(),
            ]
        );
    }

    private function touchMeta(string $waId, ?string $name, ?int $userId): void
    {
        $patch = [];
        if ($name)   { $patch['display_name'] = $name; }
        if ($userId) { $patch['user_id'] = $userId; }
        if ($patch) {
            $patch['updated_at'] = now();
            DB::table('wa_contact_issues')->where('wa_id', $waId)->update($patch);
        }
    }

    /** Match on the last 10 digits so stored phone formats do not matter. */
    private function lookupUserId(string $waId): ?int
    {
        $tail = substr($waId, -10);
        return strlen($tail) >= 9
            ? User::where('phone', 'like', "%{$tail}")->value('id')
            : null;
    }

    private function lookupName(string $waId, ?int $userId): ?string
    {
        if ($userId) {
            return User::find($userId)?->name;
        }
        $id = $this->lookupUserId($waId);
        return $id ? User::find($id)?->name : null;
    }
}
