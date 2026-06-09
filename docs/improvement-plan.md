# Notes — Improvement & Growth Plan

> Package: capell-app/notes · Kind: package · Tier: premium · Product group: Capell Collaboration · Bundle: collaboration · Status: Draft

## 1. Snapshot

Notes is an admin-only collaboration package that attaches contextual notes to Capell admin records via a polymorphic `subject` morph (default subject: `Capell\Core\Models\Page`). It owns four tables (`notes`, `note_assignments`, `note_mentions`, `note_reminders`) and four models, all morph-based for both subject and participant (`author`, `assignee`, `mentioned`, `assigned_by`, `mentioned_by`). Surfaces include a Filament `NotesInboxPage` with attention tiles, a filterable note list, resolve/reopen/complete controls, a user-menu badge, and a "Add note" header action contributed onto the page `EditPage` via the admin `ResourceHeaderActionExtender` tag. Key Actions: `CreateNoteAction`, `AssignNoteUsersAction`, `MentionNoteUsersAction`, `BuildUserAttentionCountsAction`, `BuildUserInboxNotesAction`, `MarkNoteMentionsReadAction`; dep is `capell-app/admin`. Current marketplace summary is now honest about shipped scope: _"Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention."_ — the 2026-06-06 runner pass exposed and fixed a populated-inbox Blade namespace bug, then recaptured and promoted Capell runner PNGs for populated inbox, attention counts, empty inbox, user-menu attention badge, and the record-level Add note modal. The screenshot runner now executes package setup/demo commands, and `capell:notes-demo --force` seeds deterministic assignment, mention, reminder, and resolved-note records before capture.

## 2. Improvements (existing functionality)

Prioritized.

1. **Done/Shipped: Surface actual notes in the inbox, not just counts** — `NotesInboxPage` renders attention tiles plus a filterable note list that shows body excerpts, subject, author, assignments, and mentions. Displayed mentions are marked read after preserving the initial badge counts. Evidence: `tests/Feature/Filament/NotesInboxPageTest.php` covers render, private-note scoping, and status-filter mention reads. — `src/Filament/Pages/NotesInboxPage.php`, `resources/views/filament/pages/notes-inbox.blade.php` — **L**

2. **Done/Shipped: Wire resolve/reopen/complete into the UI** — the inbox list now calls `ResolveNoteAction`, `ReopenNoteAction`, and `CompleteNoteAssignmentAction` through current-user-scoped Livewire methods. Evidence: `NotesInboxPageTest` asserts the buttons render and the page delegates each lifecycle operation. — `src/Filament/Pages/NotesInboxPage.php`, `resources/views/filament/pages/notes-inbox.blade.php` — **M**

3. **Done/Shipped: Mark mentions read** — `MarkNoteMentionsReadAction` marks only displayed notes for the current user, and the inbox calls it on mount/status-filter changes so the mention badge can clear. Evidence: `tests/Integration/Actions/UserInboxNotesActionTest.php` and `NotesInboxPageTest`. — `src/Actions/MarkNoteMentionsReadAction.php`, `src/Filament/Pages/NotesInboxPage.php` — **S**

4. **Shipped 2026-06-06: attention counts share a request-scoped cache.** `UserAttentionCountsCache` now owns per-user request memoization for `BuildUserAttentionCountsAction`, and both the admin user-menu badge and `NotesInboxPage` resolve counts through that cache. Inbox lifecycle actions clear the cached entry before re-rendering counts. — `src/Support/UserAttentionCountsCache.php`, `src/Providers/AdminServiceProvider.php`, `src/Filament/Pages/NotesInboxPage.php` — **S**

5. **Shipped 2026-06-06: note enums implement Filament labels.** `NoteStatus`, `NoteVisibility`, and `NoteReminderRecurrence` now implement `HasLabel`, and the page header action derives visibility options from the enum instead of hand-building translated arrays. — `src/Enums/NoteStatus.php`, `src/Enums/NoteVisibility.php`, `src/Enums/NoteReminderRecurrence.php`, `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php` — **S**

6. **Shipped 2026-06-06: user dropdowns use lazy searchable queries.** Assignee and mention selects now use `getSearchResultsUsing()` and `getOptionLabelsUsing()` so the Add note modal no longer eager-loads two separate `limit(100)` user lists on open. Selected labels remain resolvable by key. — `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php` — **S**

7. **Shipped 2026-06-06: Add note can be opted into beyond page EditPage.** `NotesManager::registerSubject()` now accepts resource header page classes, `CreateNoteResourceHeaderActionExtender::supports()` reads that registry, and the action accepts any registered Eloquent subject that the current user can update. The package still registers Capell `Page` + `EditPage` by default, while host packages can register their own subject/edit-page pair through `CapellNotes`. — `src/Support/NotesManager.php`, `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php`, `src/Providers/NotesServiceProvider.php` — **M**

8. **Tighten the health check label or implement real checks** — `NotesHealthCheck` only declares `compatibleCapellApiVersion()` (identical to sibling packages like comments/insights, so it is convention-correct, not a bug), but the manifest labels it _"surfaces, providers, and install health are discoverable by Diagnostics"_ at `severity: critical`. Either downgrade the label to match what the contract actually verifies, or extend it if the host contract grows assertion hooks. — `src/Health/NotesHealthCheck.php`, `capell.json` healthChecks — **S**

## 3. Missing Features (gaps)

Manifest advertises `capabilities: ["notes", "notes-admin"]` and the summary now promises private notes, assignments, and mentions. Against that and internal-notes norms:

- **Shipped 2026-06-06: reminders have a create/schedule/notify path.** The Add note modal now accepts due time, recurrence, and timezone fields, `CreateNoteAction` persists them through `UpsertNoteReminderAction`, and `capell:notes:send-due-reminders` runs `SendDueNoteReminderNotificationsAction` on a five-minute schedule. Due reminders notify active assignees, write `last_notified_at`, and advance `next_due_at` for recurring reminders. — `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php`, `src/Actions/UpsertNoteReminderAction.php`, `src/Actions/SendDueNoteReminderNotificationsAction.php`, `src/Console/SendDueNoteRemindersCommand.php`
- **Rich text remains absent.** Body is a plain `text` column and now renders as an escaped inbox excerpt, but there is no rich text, markdown, or @mention autocomplete inside the body (mentions are a separate multi-select, not inline). Table-stakes for a fuller "notes" product.
- **No activity feed / threaded replies / comments on a note.** A note is a single immutable body with no follow-ups. Internal-notes norms expect a thread. Gap vs the sibling `comments` package — consider cross-linking.
- **No attachments.** No file/image attachment to a note despite "contextual notes" framing. Table-stakes.
- **No pinning / ordering.** No way to pin an important note to the top of a record. Table-stakes.
- **`Dismissed` and `Archived` statuses + `archived_at` are dead.** Defined in `NoteStatus` and the schema but never written or surfaced. Either implement archive/dismiss actions or trim the enum/column.
- **Shipped 2026-06-06: per-note visibility has record-editor semantics.** `CanViewNoteAction` allows authors, active assignees, and mentioned users to see their notes, keeps private notes restricted to those participants, and treats `RecordEditors` notes as visible to users who can `update` the subject record. `BuildSubjectNotesAction` exposes that policy-backed rule for future record-level note display.
- **Shipped 2026-06-06: assignment and mention notifications.** `AssignNoteUsersAction` and `MentionNoteUsersAction` now send queued `NoteAttentionNotification` messages after persistence, skipping self-notifications. Delivery channels are configurable through `capell-notes.notifications.channels` and default to database notifications, with mail rendering available when `mail` is enabled. `assigned_by`/`mentioned_by` actor metadata now drives notification suppression.
- **No roles/permissions.** `capell.json` declares `permissions: []`; note creation is gated only by the subject's `update` policy. No dedicated capability to view/manage notes, no admin override.

## 4. Issues / Risks

- **Advertised capability unreachable (dead feature).** Reminders ship as schema + data + read-side aggregation with no producer — see §3. Highest-credibility risk for a paid tier. — `src/Actions/BuildUserAttentionCountsAction.php:63`, `src/Models/NoteReminder.php`, `capell.json` marketplace.summary.
- **Closed: lifecycle Actions now have a production caller.** `ResolveNoteAction`, `ReopenNoteAction`, and `CompleteNoteAssignmentAction` are reachable from the inbox list and covered by `NotesInboxPageTest`. Remaining lifecycle gaps are the unimplemented dismiss/archive statuses. — `src/Filament/Pages/NotesInboxPage.php`.
- **Admin-only visibility is partially test-proven.** The inbox render is authenticated-admin-only through Filament and `NotesInboxPageTest` proves another participant's private note is not shown. Still add a public-output safety assertion if a future public route, frontend hook, or record-level rendered component is introduced. — `docs/overview.md:38`, `resources/boost/guidelines/core.blade.php`.
- **Closed for plain text: body length and escaped display.** `CreateNoteAction` caps body length at 5,000 characters and the inbox renders escaped excerpts. If rich-text display lands, add sanitizer coverage at that boundary. — `src/Actions/CreateNoteAction.php`, `resources/views/filament/pages/notes-inbox.blade.php`.
- **Shipped 2026-06-06: polymorphic orphan cleanup.** Registered note subjects now attach a deleting hook that deletes notes for the removed subject through `PruneNotesForDeletedSubjectAction`. Registered participants attach a deleting hook that deletes authored notes, removes direct assignment/mention rows, and clears nullable `assigned_by`/`mentioned_by` actor references through `PruneNotesForDeletedParticipantAction`. Note-owned child rows still cascade through the existing `Note::deleting` hook and database FKs. — `src/Support/NotesManager.php`, `src/Actions/PruneNotesForDeletedSubjectAction.php`, `src/Actions/PruneNotesForDeletedParticipantAction.php`.
- **Performance budget cited, partially at risk.** Manifest `performance.adminQueryBudget: 40`, `frontendRenderBudgetMs: 0` (admin-only, correct). `cacheSafety.cacheable: false`. The badge + page double-run the 4-count action (§2.4); once the inbox lists notes with morph eager-loads it must stay within budget — add a query-count assertion test. — `capell.json` performance.
- **Test gaps.** Covered: CreateNote (+rollback + max length), assign/mention upsert + reactivate, complete-assignment, resolve/reopen, attention counts, mention `read_at` clearing, inbox page render/lifecycle delegation/private-note scoping, model casts/relations, migrations (guarded + cascade), manager registration, data objects, provider registration, the header-action extender. **Not covered:** reminder lifecycle (none exists), record-editor visibility semantics outside the attention inbox, public-output safety for any future public surface, orphaned-morph behavior. — `tests/`.
- **i18n.** `note.php` + `navigation.php` + `package.php` exist for `en` only; strings are translated (good), but `class_basename` fallback label in `userLabel()` and the hard-coded `'UTC'` reminder timezone default are not localized/configurable. — `resources/lang/en/`, `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php:148`.
- **`php: ^8.3` in composer vs PHP 8.4 house standard.** Minor: package allows 8.3 while the platform targets 8.4; uses `#[Override]` and typed properties that are 8.3-safe, so fine, but confirm intended floor. — `composer.json`.

## 5. Marketplace & Selling

**Current `summary`:** _"Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention."_ This now matches the shipped notes/assignments/mentions scope and avoids the unbuilt reminder producer. **Composer `description`:** _"Private, assignable notes and @mentions for any Capell admin record"_ — aligned in scope, intentionally shorter for package-manager listings.

**Improved 1-sentence summary:**

> Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention.

**Improved 3–4 sentence description:**

> Notes turns Capell admin records into a collaboration surface: leave a contextual note on a page (or any opted-in record), assign it to teammates, and mention people who need to weigh in. A per-user inbox and user-menu badge surface what's assigned to you and where you've been mentioned, so nothing slips between editors. Notes are strictly admin-internal with per-note visibility (record-editors or private) and are never exposed on the public site. Built the Capell way — domain logic in Actions, polymorphic attachment to any registered subject — so it extends cleanly into your own resources.
> _(Drop "reminders" from copy until the reminder producer ships; then re-add with "scheduled reminders".)_

**Screenshot/media status:** `capell.json` now lists the extension card plus Capell runner captures for the populated Notes inbox, opened user-menu attention badge, record-level Add note modal, attention counts/lifecycle controls, empty inbox, and dark-mode workflow. `capell:notes-demo --force` seeds deterministic collaboration records before capture so the badge and inbox screenshots show real assigned, mentioned, overdue, and resolved note states.

**Pricing / tier / bundle positioning:** Correctly a **utility within the `collaboration` bundle**, premium tier, requires `capell-app/admin`. This is a **bundled, not standalone** play — its value compounds with admin breadth and the `comments` package. Standalone pricing is weak until reminders + record-level display land. **Cross-sell:** bundle with `comments` (public-facing discussion) as an "internal vs external conversation" pair; pair with any admin-heavy Extension Suite (editorial/workflow). Lead-in capability: once notes attach to arbitrary records, every other premium package's resources become a notes surface.

**Differentiators / value props / target buyer:** Differentiator = admin-internal, per-record, per-note-visibility collaboration native to Capell (not a bolt-on). Target buyer = teams with multiple editors/reviewers doing editorial or content-ops handoffs. Value props: contextual handoff, mention-driven attention, zero public leakage.

**Keywords/tags (8–12):** `notes`, `internal-notes`, `collaboration`, `mentions`, `assignments`, `admin`, `editorial-workflow`, `content-ops`, `team`, `filament`, `polymorphic`.

## 6. Prioritized Roadmap

| Item                                                                        | Bucket | Effort | Impact | Section ref |
| --------------------------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Render notes list in inbox (view/open notes)                                | Done   | L      | High   | §2.1        |
| Wire resolve/reopen/complete actions into UI                                | Done   | M      | High   | §2.2        |
| Mark mentions read (clear the badge)                                        | Done   | S      | High   | §2.3, §3    |
| Reconcile reminders: build producer before re-advertising reminders         | Done   | M–L    | High   | §3, §4, §5  |
| Add record-editor visibility semantics for future record-level note display | Done   | M      | High   | §4          |
| Body rich-text sanitization if/when rich text ships                         | Later  | S      | Med    | §4          |
| Consolidate/cache attention counts (badge + page)                           | Done   | S      | Med    | §2.4        |
| Searchable user selects (drop limit(100))                                   | Done   | S      | Med    | §2.6        |
| Adopt enum labels (status/visibility/recurrence)                            | Done   | S      | Med    | §2.5        |
| Generalize "Add note" beyond page EditPage to any subject                   | Done   | M      | High   | §2.7        |
| Notifications on assign/mention (database/email)                            | Done   | M      | Med    | §3          |
| Orphaned-morph cleanup on subject/author delete                             | Done   | M      | Med    | §4          |
| Promote dedicated user-menu badge and record-level Add note modal captures  | Done   | S      | Med    | §5          |
| Note threads/replies + attachments                                          | Later  | L      | Med    | §3          |
| Pinning + dismiss/archive lifecycle (use dead enum cases)                   | Later  | M      | Low    | §3          |
| Per-note view/manage permissions (manifest permissions: [])                 | Later  | M      | Med    | §3          |
