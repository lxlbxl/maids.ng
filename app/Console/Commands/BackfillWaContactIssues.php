<?php

namespace App\Console\Commands;

use App\Services\WaContactIssueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Adopt existing WhatsApp issues into the contact→thread map.
 *
 * Turning the unified thread on without this would orphan every conversation
 * that already exists: the next inbound message would open a second issue for a
 * person we have been talking to for weeks. This claims the current thread for
 * each number instead, newest first, so history carries forward.
 *
 * Issues with no phone number anywhere in them (45 of 115 at the time of
 * writing — mostly July/August test rows titled "WA: conversation <uuid>") are
 * left alone. They cannot be attributed to a person by any means.
 */
class BackfillWaContactIssues extends Command
{
    protected $signature = 'wa:backfill-contact-issues {--apply : Write (default is a dry run)}';
    protected $description = 'Map existing WhatsApp issues to their contact so threads stay unified';

    /**
     * Pull a phone number out of an issue, structurally rather than by grepping
     * for digits.
     *
     * A bare "10+ digits" match is wrong here: the legacy test rows are titled
     * "WA: conversation post-restart-1785168687" and "wacrm-sim-1784244429757",
     * whose trailing numbers are unix timestamps. Read them as phone numbers and
     * the backfill invents contacts that do not exist. So we only accept digits
     * sitting where a phone number actually sits.
     */
    private function extractPhone(object $issue): ?string
    {
        // 1. The machine-readable field, when the writer left one.
        $desc = json_decode($issue->description ?? '', true);
        if (is_array($desc)) {
            foreach (['wa_id', 'phone', 'contact_phone'] as $k) {
                if (!empty($desc[$k]) && preg_match('/(\d{10,15})/', (string) $desc[$k], $m)) {
                    return $m[1];
                }
            }
            // contact_id is often "<number>@s.whatsapp.net"
            if (!empty($desc['contact_id']) && preg_match('/^(\d{10,15})(?:@|$)/', (string) $desc['contact_id'], $m)) {
                return $m[1];
            }
        }

        $title = $issue->title ?? '';

        // 2. "WA: conversation <uuid>" is the legacy test shape — never a contact.
        if (preg_match('/^(\[[^\]]+\]\s*)?WA:\s*conversation\b/i', $title)) {
            return null;
        }

        // 3. "WA: <name> — <phone>" — the dominant agent-created format.
        if (preg_match('/—\s*\+?(\d{10,15})\s*$/u', $title, $m)) {
            return $m[1];
        }

        // 4. "[MAIDS] WA: Maids-2348032200153" / "WA: evo-2348032200153"
        if (preg_match('/WA:\s*[A-Za-z][\w]*-\+?(\d{10,15})\s*$/', $title, $m)) {
            return $m[1];
        }

        return null;
    }

    public function handle(WaContactIssueService $svc): int
    {
        $apply = (bool) $this->option('apply');
        $this->line($apply ? '<fg=red>APPLYING</>' : '<fg=yellow>DRY RUN</> — pass --apply to commit.');

        $issues = DB::connection('paperclip')->table('issues')
            ->where('company_id', 'ada987c3-793e-4e0c-92fd-db3acc1a2f74')
            ->whereNull('hidden_at')
            ->where(function ($q) {
                $q->where('title', 'like', '%WA:%')
                  ->orWhere('origin_kind', 'like', '%whatsapp%');
            })
            ->orderByDesc('updated_at')        // newest wins the number
            ->get(['id', 'identifier', 'title', 'description', 'updated_at']);

        $claimed = 0; $skipped = 0; $noPhone = 0;
        // Tracked in memory as well as in the table so a dry run reports the
        // same collisions the real run would hit — otherwise the preview shows
        // one number claimed twice and says nothing was skipped.
        $seen = [];

        foreach ($issues as $i) {
            $raw = $this->extractPhone($i);
            if ($raw === null) {
                $noPhone++;
                continue;
            }

            $waId = $svc->normalize($raw);
            if ($waId === '') { $noPhone++; continue; }

            if (isset($seen[$waId]) || DB::table('wa_contact_issues')->where('wa_id', $waId)->exists()) {
                $skipped++;   // a newer issue already claimed this number
                continue;
            }
            $seen[$waId] = true;

            $name = null;
            if (preg_match('/WA:\s*(.+?)\s+—/u', $i->title, $nm)) {
                $name = trim($nm[1]);
            }

            $this->line(sprintf('  %-14s -> %-10s %s', $waId, $i->identifier ?? '-', substr($i->title, 0, 44)));

            if ($apply) {
                DB::table('wa_contact_issues')->insert([
                    'wa_id'            => $waId,
                    'issue_id'         => $i->id,
                    'issue_identifier' => $i->identifier,
                    'display_name'     => $name,
                    'origin'           => 'adopted',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
            $claimed++;
        }

        $this->newLine();
        $this->info(($apply ? 'Mapped ' : 'Would map ') . "{$claimed} contact(s).");
        $this->line("  {$skipped} issue(s) skipped — a newer thread already owns that number.");
        $this->line("  {$noPhone} issue(s) carry no phone number and cannot be attributed.");

        if (!$apply) { $this->comment('Re-run with --apply to commit.'); }

        return self::SUCCESS;
    }
}
