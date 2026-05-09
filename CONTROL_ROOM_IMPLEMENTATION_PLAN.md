# Control Room — Detailed Implementation Plan

> Based on `MAIDS_NG_CONTROL_ROOM_GUIDE.md` v1.0
> Baseline: `v2-refactor` branch — no Control Room code exists yet
> All agents exist in `app/Services/Agents/` but none have logging/integration

---

## Task Breakdown by Phase

### Phase 1 — Database Foundation

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P1.1 | Create `agent_events` migration | `database/migrations/2026_05_02_000001_create_agent_events_table.php` | None |
| P1.2 | Create `human_task_queue` migration | `database/migrations/2026_05_02_000002_create_human_task_queue_table.php` | P1.1 (FK to agent_events) |
| P1.3 | Create `agent_overrides` migration | `database/migrations/2026_05_02_000003_create_agent_overrides_table.php` | None |
| P1.4 | Create `AgentEvent` Eloquent model | `app/Models/AgentEvent.php` | P1.1 |
| P1.5 | Create `AgentOverride` Eloquent model | `app/Models/AgentOverride.php` | P1.3 |
| P1.6 | Create `HumanTask` Eloquent model | `app/Models/HumanTask.php` | P1.2 |
| P1.7 | Create `AgentOverrideSeeder` | `database/seeders/AgentOverrideSeeder.php` | P1.3, P1.5 |
| P1.8 | Run migrations + seeder | `php artisan migrate` + `php artisan db:seed --class=AgentOverrideSeeder` | P1.1–P1.7 |
| P1.9 | Verify: DB tables exist, seeder populated 10 rows | Manual check | P1.8 |

---

### Phase 2 — Agent Logging Infrastructure

| # | Task | Files to Create/Modify | Dependencies |
|---|------|----------------------|-------------|
| P2.1 | Create `AgentEventLogger` service | `app/Services/AgentEventLogger.php` | P1.4, P1.5, P1.6 |
| P2.2 | Create `ActionDispatcher` service | `app/Services/ActionDispatcher.php` | P2.1, P1.5, P1.6 |
| P2.3 | Create `LogsEvents` trait | `app/Agents/Concerns/LogsEvents.php` | P2.1, P2.2 |
| P2.4 | Register services as singletons | Edit `app/Providers/AppServiceProvider.php` | P2.1, P2.2 |
| P2.5 | Integrate logging into `ScoutAgent` | Edit `app/Services/Agents/ScoutAgent.php` | P2.3 |
| P2.6 | Integrate logging into `AmbassadorAgent` | Edit `app/Services/Agents/AmbassadorAgent.php` | P2.3 |
| P2.7 | Integrate logging into `TreasurerAgent` | Edit `app/Services/Agents/TreasurerAgent.php` | P2.3 |
| P2.8 | Integrate logging into `GatekeeperAgent` | Edit `app/Services/Agents/GatekeeperAgent.php` | P2.3 |
| P2.9 | Integrate logging into `SentinelAgent` | Edit `app/Services/Agents/SentinelAgent.php` | P2.3 |
| P2.10 | Integrate logging into `RefereeAgent` | Edit `app/Services/Agents/RefereeAgent.php` | P2.3 |
| P2.11 | Integrate logging into `ConciergeAgent` | Edit `app/Services/Agents/ConciergeAgent.php` | P2.3 |
| P2.12 | Add daily spend reset to scheduler | Edit `routes/console.php` | P1.5 |
| P2.13 | Verify: `AgentEvent::create()` works, cost calc works | Test | P2.1–P2.11 |

> **Note for P2.9–P2.11 (Content agents):** The Guide references `MarketerAgent`, `SeoContentAgent`, and `OutreachEngine` as separate agent classes. These may not exist as standalone agent classes yet. If they don't exist:
> - Create `app/Services/Agents/MarketerAgent.php` 
> - Create `app/Services/Agents/SeoContentAgent.php`
> - Create `app/Services/Agents/OutreachEngine.php`
> Otherwise, add logging directly to the jobs that generate content (`app/Jobs/GenerateSocialContent` equivalent, `app/Services/SeoContentGenerator.php`, `app/Jobs/` outreach-related jobs).

---

### Phase 3 — Human Override & Fallback System

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P3.1 | Create `AgentOverrideService` | `app/Services/AgentOverrideService.php` | P2.1, P1.5 |
| P3.2 | Create `HumanExecutionService` | `app/Services/HumanExecutionService.php` | P2.1, P1.6, P2.5–P2.11 |
| P3.3 | Verify: Pause/resume/kill-switch functions work | Test via tinker | P3.1 |
| P3.4 | Verify: Human execution routes to correct services | Test | P3.2 |

---

### Phase 4 — SSE Real-Time Event Stream

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P4.1 | Create `EventStreamController` | `app/Http/Controllers/Admin/AgentControlRoom/EventStreamController.php` | P1.4 |
| P4.2 | Verify SSE endpoint returns `text/event-stream` | Browser/curl test | P4.1 |
| P4.3 | Verify new events appear in stream within 4s | Create AgentEvent, check stream | P4.1 |

---

### Phase 5 — Control Room Controllers

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P5.1 | Create `ControlRoomController` (main page + all actions) | `app/Http/Controllers/Admin/AgentControlRoom/ControlRoomController.php` | P3.1, P3.2, P1.4–P1.6 |
| P5.2 | Add `showHitlTask` method for the dedicated HITL detail page | Edit P5.1 | P5.1 |

---

### Phase 6 — React Control Room UI (Five Panels)

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P6.1 | Create main `ControlRoom/Index.jsx` page | `resources/js/Pages/Admin/ControlRoom/Index.jsx` | P4.1, P5.1 |
| P6.2 | Create `AgentControlBar` component | `resources/js/Pages/Admin/ControlRoom/Components/AgentControlBar.jsx` | P6.1 |
| P6.3 | Create `LiveFeedPanel` component | `resources/js/Pages/Admin/ControlRoom/Panels/LiveFeedPanel.jsx` | P6.1 |
| P6.4 | Create `QueueHealthPanel` component | `resources/js/Pages/Admin/ControlRoom/Panels/QueueHealthPanel.jsx` | P6.1 |
| P6.5 | Create `HumanTaskPanel` component (with `TaskExecutionForm`) | `resources/js/Pages/Admin/ControlRoom/Panels/HumanTaskPanel.jsx` | P6.1 |
| P6.6 | Create `CampaignCommandPanel` component | `resources/js/Pages/Admin/ControlRoom/Panels/CampaignCommandPanel.jsx` | P6.1 |
| P6.7 | Create `TokenCostPanel` component | `resources/js/Pages/Admin/ControlRoom/Panels/TokenCostPanel.jsx` | P6.1 |

---

### Phase 7 — Human Task Execution Interface (Dedicated Page)

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P7.1 | Create `HumanTask/Show.jsx` full-page view | `resources/js/Pages/Admin/ControlRoom/HumanTask/Show.jsx` | P5.1 (showHitlTask) |
| P7.2 | Create controller method `showHitlTask` (if not added in P5.2) | Edit `ControlRoomController.php` | P5.1 |

---

### Phase 8 — Agent Kill Switches & Override Controls

| # | Task | Files to Create | Dependencies |
|---|------|----------------|-------------|
| P8.1 | Create `EmergencyStopAllAgents` command | `app/Console/Commands/EmergencyStopAllAgents.php` | P1.5, P2.1 |
| P8.2 | Create `ResumeAllAgents` command | `app/Console/Commands/ResumeAllAgents.php` | P1.5, P2.1 |
| P8.3 | Create `CheckAiProviderHealth` job | `app/Jobs/CheckAiProviderHealth.php` | P1.5, P2.1 |
| P8.4 | Create `AiProviderDownAlert` mail | `app/Mail/AiProviderDownAlert.php` | P8.3 |
| P8.5 | Schedule the health check job | Edit `routes/console.php` | P8.3 |
| P8.6 | Verify: `php artisan agents:emergency-stop "test"` works | CLI test | P8.1 |
| P8.7 | Verify: `php artisan agents:resume-all` works | CLI test | P8.2 |

---

### Phase 9 — Routes & Registration

| # | Task | Files to Create/Modify | Dependencies |
|---|------|----------------------|-------------|
| P9.1 | Create `routes/control_room.php` with all routes | `routes/control_room.php` | P5.1, P4.1 |
| P9.2 | Include control_room routes in web.php | Edit `routes/web.php` | P9.1 |
| P9.3 | Add `controlRoom` shared data to middleware | Edit `app/Http/Middleware/HandleInertiaRequests.php` | P1.6, P1.4 |
| P9.4 | Add "Control Room" nav link to AdminLayout | Edit `resources/js/Layouts/AdminLayout.jsx` | P9.3 |
| P9.5 | Verify: All admin routes return 403 for non-admin users | Test | P9.1–P9.4 |
| P9.6 | Verify: `/admin/control-room` loads all 5 panels | Browser test | P9.1–P9.4 |

---

## Implementation Order (Critical Path)

```
Phase 1 (DB) → Phase 2 (Logging) → Phase 3 (Overrides/Execution) → Phase 5 (Controllers)
                                                                       ↓
                              Phase 4 (SSE) ───────────────────────────┤
                                                                       ↓
                              Phase 6 (UI) ────────────────────────────┤
                                                                       ↓
                              Phase 7 (HITL detail page) ──────────────┤
                                                                       ↓
                              Phase 8 (Commands/jobs) ─────────────────┤
                                                                       ↓
                              Phase 9 (Routes/nav) ────────────────────┘
```

**Phases 1→2→3→5 must be sequential.** Phases 4, 8 can run in parallel with 5. Phase 6 requires 4+5. Phase 7 requires 5. Phase 9 is last.

---

## Files to Create: Complete Inventory

### New files (35 total)

**Backend — Migrations (3):**
1. `database/migrations/2026_05_02_000001_create_agent_events_table.php`
2. `database/migrations/2026_05_02_000002_create_human_task_queue_table.php`
3. `database/migrations/2026_05_02_000003_create_agent_overrides_table.php`

**Backend — Models (3):**
4. `app/Models/AgentEvent.php`
5. `app/Models/AgentOverride.php`
6. `app/Models/HumanTask.php`

**Backend — Seeders (1):**
7. `database/seeders/AgentOverrideSeeder.php`

**Backend — Services (4):**
8. `app/Services/AgentEventLogger.php`
9. `app/Services/ActionDispatcher.php`
10. `app/Services/AgentOverrideService.php`
11. `app/Services/HumanExecutionService.php`

**Backend — Agent Trait (1):**
12. `app/Agents/Concerns/LogsEvents.php`

**Backend — Controllers (2):**
13. `app/Http/Controllers/Admin/AgentControlRoom/ControlRoomController.php`
14. `app/Http/Controllers/Admin/AgentControlRoom/EventStreamController.php`

**Backend — Commands (2):**
15. `app/Console/Commands/EmergencyStopAllAgents.php`
16. `app/Console/Commands/ResumeAllAgents.php`

**Backend — Jobs (1):**
17. `app/Jobs/CheckAiProviderHealth.php`

**Backend — Mail (1):**
18. `app/Mail/AiProviderDownAlert.php`

**Backend — Routes (1):**
19. `routes/control_room.php`

**Frontend — Pages (8):**
20. `resources/js/Pages/Admin/ControlRoom/Index.jsx`
21. `resources/js/Pages/Admin/ControlRoom/HumanTask/Show.jsx`
22. `resources/js/Pages/Admin/ControlRoom/Components/AgentControlBar.jsx`
23. `resources/js/Pages/Admin/ControlRoom/Panels/LiveFeedPanel.jsx`
24. `resources/js/Pages/Admin/ControlRoom/Panels/QueueHealthPanel.jsx`
25. `resources/js/Pages/Admin/ControlRoom/Panels/HumanTaskPanel.jsx`
26. `resources/js/Pages/Admin/ControlRoom/Panels/CampaignCommandPanel.jsx`
27. `resources/js/Pages/Admin/ControlRoom/Panels/TokenCostPanel.jsx`

**Potential new agent files (3, if missing):**
28. `app/Services/Agents/MarketerAgent.php` (may not exist)
29. `app/Services/Agents/SeoContentAgent.php` (may not exist)
30. `app/Services/Agents/OutreachEngine.php` (may not exist)

### Files to Modify (12+)

| # | File | What Changes |
|---|------|-------------|
| M1 | `app/Providers/AppServiceProvider.php` | Add 4 singleton bindings |
| M2 | `app/Services/Agents/ScoutAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M3 | `app/Services/Agents/AmbassadorAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M4 | `app/Services/Agents/TreasurerAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M5 | `app/Services/Agents/GatekeeperAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M6 | `app/Services/Agents/SentinelAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M7 | `app/Services/Agents/RefereeAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M8 | `app/Services/Agents/ConciergeAgent.php` | Add `use LogsEvents`, `$agentName`, logging calls |
| M9 | `routes/web.php` | Add `require __DIR__ . '/control_room.php';` |
| M10 | `routes/console.php` | Add daily spend reset + AI health check schedules |
| M11 | `app/Http/Middleware/HandleInertiaRequests.php` | Add `controlRoom` shared data |
| M12 | `resources/js/Layouts/AdminLayout.jsx` | Add Control Room nav link with badge |

---

## Risk Areas & Unknowns

1. **Content agents may not exist as classes** — The Guide references `MarketerAgent`, `SeoContentAgent`, and `OutreachEngine` as agent classes with `$agentName`. The current codebase has `app/Jobs/GenerateSeoPageRegistry.php`, `app/Jobs/RefreshSeoContent.php`, and `app/Services/SeoContentGenerator.php` but no standalone agent classes for marketing/SEO/outreach. Need to decide: create new agent classes, or log from the jobs directly.

2. **No `ChannelSender` service** — `HumanExecutionService::executeSendMessage()` references `app\Services\ChannelSender` — this may not exist. Check `app/Services/Agents/ConversationManager.php` and channel handlers for the actual send method.

3. **No `AgentCampaign` model** — The CampaignCommandPanel and human outreach tasks reference `App\Models\AgentCampaign`. This model does not exist in the current codebase. May need to create a stub or adapt the UI panel.

4. **No `SocialPost` model** — Referenced in MarketerAgent logging. Does not exist.

5. **No `AgentOutreachLog` model** — Referenced in OutreachEngine logging. Does not exist.

6. **No `\App\Services\SeoContentGenerator` with `generate()` method** — The file exists (`app/Services/SeoContentGenerator.php`) but the `generate(SeoPage $page)` API used in HumanExecutionService may differ from the actual implementation.

7. **`TransferToMaid` in WalletService** — HumanExecutionService calls `WalletService::transferToMaid()`. Need to verify this method exists in `app/Services/WalletService.php`.

8. **NIN verification table** — HumanExecutionService references `nin_verifications` table directly with `\DB::table('nin_verifications')`. The migration `2026_04_30_000004_create_nin_verifications_table.php` exists, so this should be fine.

9. **SSE sessionless middleware** — The guide says to use `->withoutMiddleware(['web'])` on the SSE route. Verify this works with the existing auth setup (token-based?).

10. **Existing agent architecture** — The agents extend `AgentService` (from `app/Services/AgentService.php`). Need to check if this class has a `$agentName` property or if it's something we add.

---

## Estimated File Count

| Category | New Files |
|----------|-----------|
| Migrations | 3 |
| Models | 3 |
| Seeders | 1 |
| Services | 4 |
| Traits | 1 |
| Controllers | 2 |
| Commands | 2 |
| Jobs | 1 |
| Mail | 1 |
| Routes | 1 |
| React components | 8 |
| **Total new files** | **27** |
| Files to modify | 12+ |

---

## Verification Checklist (from Guide's Definition of Done)

### Database & Models
- [ ] All 3 migrations run cleanly
- [ ] AgentOverride seeder populates 10 rows
- [ ] AgentEvent::create() writes readable row
- [ ] AgentOverride::forAgent() hits cache on second call

### Agent Logging Integration
- [ ] Every agent has `use LogsEvents` trait
- [ ] ScoutAgent writes `match.scored` events
- [ ] AmbassadorAgent writes `message.sent` events
- [ ] TreasurerAgent writes `pending` events for large payouts
- [ ] GatekeeperAgent writes events for all 3 NIN verdict paths
- [ ] Content agents log events with token counts
- [ ] Token costs calculated and stored

### ActionDispatcher
- [ ] Returns `'execute'` when agent is active
- [ ] Returns `'hitl'` when paused + auto_route
- [ ] Returns `'killed'` when kill_switch=true
- [ ] Returns `'skip'` on spend cap breach
- [ ] Creates HumanTask on `'hitl'` result

### Override Controls
- [ ] Pause/Resume updates DB and clears cache immediately
- [ ] Kill switch stops agent mid-operation
- [ ] `agents:emergency-stop` sets all agents killed within 1s
- [ ] `agents:resume-all` restores all within 1s
- [ ] Spend cap detected before LLM call (no cost incurred)

### SSE Stream
- [ ] Returns `Content-Type: text/event-stream`
- [ ] New events appear in feed within 4s
- [ ] Auto-reconnect works
- [ ] Queue health events emitted every 10s

### Human Task Execution
- [ ] Send message task sends on correct channel
- [ ] Verify NIN task shows decision dropdown and updates DB
- [ ] Process payout task calls WalletService
- [ ] Skip task removes from queue
- [ ] Failed execution returns error, resets to pending

### AI Downtime Detection
- [ ] Health check job runs every 5 min
- [ ] All active agents switch to supervised on dual-provider failure
- [ ] Downtime event logged
- [ ] Admin email sent

### Control Room UI
- [ ] Page loads without JS errors
- [ ] Control Bar shows correct mode colours
- [ ] Pausing agent updates badge without reload
- [ ] Live Feed shows events within 4s
- [ ] Click "Expand" shows detail JSON
- [ ] Queue Health shows correct 24h stats
- [ ] HITL pending badge in sidebar nav
- [ ] Inline form renders different fields per task_type
- [ ] Executing task updates count without reload
- [ ] Token Cost shows today's total + per-agent breakdown
- [ ] Campaign Command toggles campaigns
- [ ] All admin routes return 403 for non-admins
