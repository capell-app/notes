# Notes — Improvement & Growth Plan

> Package: capell-app/notes · Kind: package · Tier: premium · Product group: Capell Collaboration · Bundle: collaboration · Status: Draft

## 1. Snapshot

Notes is an admin-only collaboration package that attaches contextual notes to Capell admin records via a polymorphic `subject` morph (default subject: `Capell\Core\Models\Page`). It owns four tables (`notes`, `note_assignments`, `note_mentions`, `note_reminders`) and four models, all morph-based for both subject and participant (`author`, `assignee`, `mentioned`, `assigned_by`, `mentioned_by`). Surfaces are limited to a single Filament `NotesInboxPage` (4 count tiles) plus a user-menu badge, and a "Add note" header action contributed onto the page `EditPage` via the admin `ResourceHeaderActionExtender` tag. Key Actions: `CreateNoteAction`, `AssignNoteUsersAction`, `MentionNoteUsersAction`, `BuildUserAttentionCountsAction`; dep is `capell-app/admin`. Current marketplace summary (verbatim): _"Notes adds contextual notes, assignments, mentions, and reminders to supported Capell admin records."_ — `capell.json` declares **1** screenshot (`docs/assets/marketplace/extension-card.jpg`) while `docs/screenshots.json` and `docs/overview.md` plan **4**; the `docs/screenshots/` directory does not exist (mismatch).

## 2. Improvements (existing functionality)

Prioritized.

1. **Surface actual notes in the inbox, not just counts** — `NotesInboxPage` renders 4 tiles (`assigned`, `mentions`, `due_today`, `overdue`) and nothing else; an admin cannot see, open, resolve, or read any note from the inbox. This is the headline product surface and it is currently a dead-end dashboard. Add a notes table/list (assigned-to-me, mentioned-me, filterable by status) below the tiles. — `src/Filament/Pages/NotesInboxPage.php`, `resources/views/filament/pages/notes-inbox.blade.php` — **L**

2. **Wire resolve/reopen/complete into the UI** — `ResolveNoteAction`, `ReopenNoteAction`, `CompleteNoteAssignmentAction` exist and are tested but have **zero callers** outside tests (verified by grep across `src/`). The status lifecycle is unreachable in production. Add row/record actions on the inbox list (and/or on the subject record) to resolve/reopen a note and complete an assignment. — `src/Actions/ResolveNoteAction.php`, `src/Actions/ReopenNoteAction.php`, `src/Actions/CompleteNoteAssignmentAction.php` — **M**

3. **Mark mentions read** — `note_mentions.read_at` is written as `null` on create and counted in `mentions` attention, but nothing ever sets `read_at`. The mention badge can only grow, never clear. Add a `MarkMentionReadAction` and call it when a user views the note/inbox. — `src/Actions/MentionNoteUsersAction.php` (sibling), `src/Actions/BuildUserAttentionCountsAction.php:52` — **S**

4. **Cache attention counts across the request lifecycle** — `AdminServiceProvider` memoizes per request, but `NotesInboxPage::counts()` re-runs `BuildUserAttentionCountsAction` independently, so the badge and the page each issue the same 4 aggregate queries (the page does not reuse the provider memo). The action runs 4 counts plus 2 `whereHas` subqueries against `note_reminders`→`note_assignments`. Consolidate on one cached path and consider a short TTL cache keyed by user morph. — `src/Providers/AdminServiceProvider.php:66`, `src/Filament/Pages/NotesInboxPage.php:43` — **S**

5. **Add enum labels via Capell/host `HasLabels` convention** — `NoteStatus`, `NoteVisibility`, `NoteReminderRecurrence` are bare backed enums; the Filament visibility options are hand-built in `visibilityOptions()`. Adopt enum labels so options, table badges, and filters derive from the enum. — `src/Enums/NoteStatus.php`, `src/Enums/NoteVisibility.php`, `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php:84` — **S**

6. **Replace unbounded user dropdowns with searchable queries** — `userOptions()` does `->limit(100)->get()` for both the assignee and mention selects, eagerly loading up to 100 users twice per modal open and silently truncating larger installs. Use `->getSearchResultsUsing()` / `->getOptionLabelsUsing()` so it scales. — `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php:93-106` — **S**

7. **Generalize the "Add note" action beyond the page EditPage** — the extender `supports()` only returns true for `Capell\Admin\Filament\Resources\Pages\Pages\EditPage`, and the only registered subject is `Page`. The polymorphic schema supports any record, but no other edit screen can add notes. Provide a reusable extender (or trait/contract) host packages can opt records into. — `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php:23`, `src/Providers/NotesServiceProvider.php:96` — **M**

8. **Tighten the health check label or implement real checks** — `NotesHealthCheck` only declares `compatibleCapellApiVersion()` (identical to sibling packages like comments/insights, so it is convention-correct, not a bug), but the manifest labels it _"surfaces, providers, and install health are discoverable by Diagnostics"_ at `severity: critical`. Either downgrade the label to match what the contract actually verifies, or extend it if the host contract grows assertion hooks. — `src/Health/NotesHealthCheck.php`, `capell.json` healthChecks — **S**

## 3. Missing Features (gaps)

Manifest advertises `capabilities: ["notes", "notes-admin"]` and the summary promises _notes, assignments, mentions, and reminders_. Against that and internal-notes norms:

- **Reminders are entirely non-functional (table-stakes vs advertised).** `NoteReminder` model, `note_reminders` table, `NoteReminderData`, and `NoteReminderRecurrence` exist, and `BuildUserAttentionCountsAction` reads `due_today`/`overdue` from reminders — but there is **no Action, Filament form, command, or any write path that creates or schedules a reminder** (grep of `src/` shows only reads + the cascade delete). The "Due today" and "Overdue" inbox tiles are therefore permanently `0`, and the user-menu badge can never turn `danger`. Either build the reminder create/schedule/notify flow (recurrence advance of `next_due_at`, a scheduled notifier writing `last_notified_at`) or remove "reminders" from the summary until shipped. **Differentiator** if delivered well (most internal-notes tools lack scheduled reminders); **false advertising** until then.
- **No note body display / rich text.** Body is a plain `text` column rendered nowhere. No rich text, no markdown, no @mention autocomplete inside the body (mentions are a separate multi-select, not inline). Table-stakes for a "notes" product.
- **No activity feed / threaded replies / comments on a note.** A note is a single immutable body with no follow-ups. Internal-notes norms expect a thread. Gap vs the sibling `comments` package — consider cross-linking.
- **No attachments.** No file/image attachment to a note despite "contextual notes" framing. Table-stakes.
- **No pinning / ordering.** No way to pin an important note to the top of a record. Table-stakes.
- **`Dismissed` and `Archived` statuses + `archived_at` are dead.** Defined in `NoteStatus` and the schema but never written or surfaced. Either implement archive/dismiss actions or trim the enum/column.
- **Per-note visibility is under-enforced.** `NoteVisibility::Private` and `RecordEditors` are stored but no read path filters on them yet (because there is no read surface). When the inbox list lands, `Private` notes must be scoped to author/assignee/mention only — this is the core admin-only safety contract and currently untested because unreachable.
- **No notifications.** No email/database notification on assignment or mention; the only feedback is the in-page badge. `assigned_by`/`mentioned_by` are captured but never used to notify.
- **No roles/permissions.** `capell.json` declares `permissions: []`; note creation is gated only by the subject's `update` policy. No dedicated capability to view/manage notes, no admin override.

## 4. Issues / Risks

- **Advertised capability unreachable (dead feature).** Reminders ship as schema + data + read-side aggregation with no producer — see §3. Highest-credibility risk for a paid tier. — `src/Actions/BuildUserAttentionCountsAction.php:63`, `src/Models/NoteReminder.php`, `capell.json` marketplace.summary.
- **Dead Actions.** `ResolveNoteAction`, `ReopenNoteAction`, `CompleteNoteAssignmentAction` have no production caller (only tests). Either wire up (§2.2) or they are untested-in-context risk. — `src/Actions/`.
- **Admin-only visibility is asserted in docs but not test-proven.** `docs/overview.md` and the boost guideline state notes must stay admin-only and never add public surface, but there is **no test** proving anonymous/non-admin cannot read note bodies — acceptable today only because there is no read surface at all. The moment the inbox list (§2.1) or any record-level note display lands, add a public-safety / visibility-scoping test before merge (Capell convention: rendering changes need anon + non-admin safety tests). — `docs/overview.md:38`, `resources/boost/guidelines/core.blade.php`.
- **No content sanitization.** `CreateNoteAction` only `trim()`s the body; no length cap, no HTML/script handling. Plain `text` storage is safe at rest, but any future rich-text or record-level rendering must escape/sanitize. Add a max-length validation now and a sanitization step when display lands. — `src/Actions/CreateNoteAction.php:49`.
- **Polymorphic integrity.** Children cascade on DB FK (`note_id` → `cascadeOnDelete`) and the model `deleting` hook also deletes children — belt-and-braces, fine. But `subject`/`author`/`assignee`/`mentioned` morphs have **no cleanup** when the _target_ (e.g. a Page or User) is deleted: notes orphan with dangling `subject_id`/`author_id`. No `morphMap` alias is registered for the note models' own morph type beyond `registerModels()`, and there is no subject-deletion subscriber. Add orphan handling (nullable author display fallback already exists via `userLabel`, but subject orphans will break the inbox list). — `database/migrations/2026_05_10_190862_01_create_notes_tables.php`, `src/Models/Note.php:92`.
- **Performance budget cited, partially at risk.** Manifest `performance.adminQueryBudget: 40`, `frontendRenderBudgetMs: 0` (admin-only, correct). `cacheSafety.cacheable: false`. The badge + page double-run the 4-count action (§2.4); once the inbox lists notes with morph eager-loads it must stay within budget — add a query-count assertion test. — `capell.json` performance.
- **Test gaps.** Covered: CreateNote (+rollback), assign/mention upsert + reactivate, complete-assignment, resolve/reopen, attention counts, model casts/relations, migrations (guarded + cascade), manager registration, data objects, provider registration, the header-action extender. **Not covered:** reminder lifecycle (none exists), mention `read_at` clearing, visibility scoping on read, public/non-admin safety, the inbox page render itself (`NotesInboxPage` has no Livewire/page test), orphaned-morph behavior, body max-length. — `tests/`.
- **i18n.** `note.php` + `navigation.php` + `package.php` exist for `en` only; strings are translated (good), but `class_basename` fallback label in `userLabel()` and the hard-coded `'UTC'` reminder timezone default are not localized/configurable. — `resources/lang/en/`, `src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php:148`.
- **`php: ^8.3` in composer vs PHP 8.4 house standard.** Minor: package allows 8.3 while the platform targets 8.4; uses `#[Override]` and typed properties that are 8.3-safe, so fine, but confirm intended floor. — `composer.json`.

## 5. Marketplace & Selling

**Current `summary`:** _"Notes adds contextual notes, assignments, mentions, and reminders to supported Capell admin records."_ — accurate on notes/assignments/mentions but **reminders are not functional** (§3), so the summary over-promises a paid capability. **Composer `description`:** _"Contextual notes, assignments, mentions, and reminders for Capell"_ — same reminder problem, and it differs in wording from the manifest summary (keep them consistent or intentionally distinct). Both lead with mechanics, not buyer value.

**Improved 1-sentence summary:**

> Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention.

**Improved 3–4 sentence description:**

> Notes turns Capell admin records into a collaboration surface: leave a contextual note on a page (or any opted-in record), assign it to teammates, and mention people who need to weigh in. A per-user inbox and user-menu badge surface what's assigned to you and where you've been mentioned, so nothing slips between editors. Notes are strictly admin-internal with per-note visibility (record-editors or private) and are never exposed on the public site. Built the Capell way — domain logic in Actions, polymorphic attachment to any registered subject — so it extends cleanly into your own resources.
> _(Drop "reminders" from copy until the reminder producer ships; then re-add with "scheduled reminders".)_

**Screenshot/media gaps:** `capell.json` lists **1** screenshot but `docs/screenshots.json`/`overview.md` plan **4** and `docs/screenshots/` doesn't exist — generate the 4 planned shots (inbox populated, user-menu badge, empty state, attention counts) and reconcile the manifest count. Crucially, the most compelling shot (notes listed on a record + the "Add note" modal) isn't even planned because the surface doesn't render notes yet — gate marketplace listing on §2.1.

**Pricing / tier / bundle positioning:** Correctly a **utility within the `collaboration` bundle**, premium tier, requires `capell-app/admin`. This is a **bundled, not standalone** play — its value compounds with admin breadth and the `comments` package. Standalone pricing is weak until reminders + record-level display land. **Cross-sell:** bundle with `comments` (public-facing discussion) as an "internal vs external conversation" pair; pair with any admin-heavy Extension Suite (editorial/workflow). Lead-in capability: once notes attach to arbitrary records, every other premium package's resources become a notes surface.

**Differentiators / value props / target buyer:** Differentiator = admin-internal, per-record, per-note-visibility collaboration native to Capell (not a bolt-on). Target buyer = teams with multiple editors/reviewers doing editorial or content-ops handoffs. Value props: contextual handoff, mention-driven attention, zero public leakage.

**Keywords/tags (8–12):** `notes`, `internal-notes`, `collaboration`, `mentions`, `assignments`, `admin`, `editorial-workflow`, `content-ops`, `reminders`, `team`, `filament`, `polymorphic`.

## 6. Prioritized Roadmap

| Item                                                                          | Bucket | Effort | Impact | Section ref |
| ----------------------------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Render notes list in inbox (view/open notes)                                  | Done   | L      | High   | §2.1        |
| Wire resolve/reopen/complete actions into UI                                  | Now    | M      | High   | §2.2        |
| Mark mentions read (clear the badge)                                          | Done   | S      | High   | §2.3, §3    |
| Reconcile reminders: build producer OR drop from copy/summary                 | Now    | S–L    | High   | §3, §4, §5  |
| Visibility scoping + anon/non-admin safety test before any read surface ships | Now    | M      | High   | §4          |
| Body max-length validation + sanitization on display                          | Now    | S      | Med    | §4          |
| Consolidate/cache attention counts (badge + page)                             | Next   | S      | Med    | §2.4        |
| Searchable user selects (drop limit(100))                                     | Next   | S      | Med    | §2.6        |
| Adopt enum labels (status/visibility/recurrence)                              | Next   | S      | Med    | §2.5        |
| Generalize "Add note" beyond page EditPage to any subject                     | Next   | M      | High   | §2.7        |
| Notifications on assign/mention (database/email)                              | Next   | M      | Med    | §3          |
| Orphaned-morph cleanup on subject/author delete                               | Next   | M      | Med    | §4          |
| Generate 4 planned screenshots + fix manifest count                           | Next   | S      | Med    | §5          |
| Note threads/replies + attachments                                            | Later  | L      | Med    | §3          |
| Pinning + dismiss/archive lifecycle (use dead enum cases)                     | Later  | M      | Low    | §3          |
| Per-note view/manage permissions (manifest permissions: [])                   | Later  | M      | Med    | §3          |
