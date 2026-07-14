# Notes

<!-- prettier-ignore-start -->

## What This Plugin Adds

Notes is an **Available**, **Schema-owning** Capell package in the **Capell Engagement & CRM** product group. It ships as `capell-app/notes` and extends these surfaces: admin.

Notes adds private record notes with assignments, mentions, reminders, completion state, and an inbox for work needing attention.

Admin users can add a note from supported records, assign colleagues, mention people, set reminders, and work through their Notes inbox.

Evidence: [`src/Actions/CreateNoteAction.php`](src/Actions/CreateNoteAction.php), [`src/Actions/AssignNoteUsersAction.php`](src/Actions/AssignNoteUsersAction.php), [`src/Actions/MentionNoteUsersAction.php`](src/Actions/MentionNoteUsersAction.php), [`src/Actions/UpsertNoteReminderAction.php`](src/Actions/UpsertNoteReminderAction.php), [`src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php`](src/Filament/Extenders/Page/CreateNoteResourceHeaderActionExtender.php), [`src/Filament/Pages/NotesInboxPage.php`](src/Filament/Pages/NotesInboxPage.php), [`tests/Feature/Filament/NotesInboxPageTest.php`](tests/Feature/Filament/NotesInboxPageTest.php), [`tests/Unit/Filament/CreateNoteResourceHeaderActionExtenderTest.php`](tests/Unit/Filament/CreateNoteResourceHeaderActionExtenderTest.php).

Status details:

- Status: Available
- Tier: premium
- Bundle: engagement-crm
- Composer package: `capell-app/notes`
- Namespace: `Capell\Notes`
- Theme key: not applicable

## Why It Matters

**For developers:** Note creation, visibility, assignment, reminders, and notifications are split into Actions behind a reusable Notes manager.

**For teams:** Editors can coordinate follow-up beside the record being discussed without exposing internal notes on the public site.

Evidence: [`src/Support/NotesManager.php`](src/Support/NotesManager.php), [`src/Actions/CanViewNoteAction.php`](src/Actions/CanViewNoteAction.php), [`src/Actions/SendDueNoteReminderNotificationsAction.php`](src/Actions/SendDueNoteReminderNotificationsAction.php), [`tests/Integration/Actions/CreateNoteActionTest.php`](tests/Integration/Actions/CreateNoteActionTest.php), [`docs/overview.admin.md`](docs/overview.admin.md), [`src/Actions/BuildSubjectNotesAction.php`](src/Actions/BuildSubjectNotesAction.php), [`tests/Integration/Actions/NoteAssignmentActionTest.php`](tests/Integration/Actions/NoteAssignmentActionTest.php).

## Screens And Workflow

Screenshot contract: `docs/screenshots.json`.

![User-menu notes item with attention badge](docs/screenshots/notes-user-menu-badge.png)

![Notes inbox page](docs/screenshots/notes-inbox-page.png)

- User-menu notes item with attention badge (admin, required).
- Notes inbox page (admin, required).
- Record-level Add note modal (admin, required).
- Empty notes inbox state (admin, required).
- Notes inbox with assigned, mentioned, and lifecycle controls (admin, optional).

## Technical Shape

- Service providers: `Capell\Notes\Providers\NotesServiceProvider`, `Capell\Notes\Providers\AdminServiceProvider`.
- Config files: `packages/notes/config/capell-notes.php`.
- Migrations: `packages/notes/database/migrations/2026_05_10_190862_01_create_notes_tables.php`, `packages/notes/database/migrations/2026_07_10_000002_encrypt_note_bodies.php`.
- Models: `Note`, `NoteAssignment`, `NoteMention`, `NoteReminder`.
- Filament classes: `CreateNoteResourceHeaderActionExtender`, `NotesInboxPage`.
- Policies: `NotePolicy`.
- Actions: `AssignNoteUsersAction`, `BuildSubjectNotesAction`, `BuildUserAttentionCountsAction`, `BuildUserInboxNotesAction`, `CanViewNoteAction`, `CompleteNoteAssignmentAction`, `CreateNoteAction`, `MarkNoteMentionsReadAction`, `MentionNoteUsersAction`, `PruneNotesForDeletedParticipantAction`, `PruneNotesForDeletedSubjectAction`, `ReopenNoteAction`, `and 6 more`.
- Data objects: `CreateNoteData`, `NoteReminderData`, `UserAttentionCountData`.
- Command signatures: `capell:notes-demo`, `capell:notes:send-due-reminders`.
- Scheduled commands: `capell:notes:send-due-reminders (everyFiveMinutes)`.
- Console command classes: `DemoCommand`, `SendDueNoteRemindersCommand`.
- Manifest contributions: `admin-action-extender: Capell\Notes\Manifest\NotesAdminActionExtenderContribution`, `admin-page: Capell\Notes\Manifest\NotesAdminPageContribution`, `console-command: Capell\Notes\Manifest\NotesConsoleCommandsContribution`, `health-check: Capell\Notes\Manifest\NotesHealthContribution`, `model: Capell\Notes\Manifest\NotesModelsContribution`, `scheduled-job: Capell\Notes\Manifest\NotesReminderScheduleContribution`.
- Health checks: `Capell\Notes\Health\NotesHealthCheck`.
- Blade views: `packages/notes/resources/views/filament/pages/notes-inbox.blade.php`.

## Data Model

- Required tables: `notes`, `note_assignments`, `note_mentions`, `note_reminders`.
- Models: `Note`, `NoteAssignment`, `NoteMention`, `NoteReminder`.
- Migration files: `2026_05_10_190862_01_create_notes_tables.php`, `2026_07_10_000002_encrypt_note_bodies.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

## Install Impact

- Required packages: `capell-app/admin`.
- Admin navigation: declares `admin-page: NotesAdminPageContribution`; each Filament page or resource controls its own navigation visibility.
- Admin/editor extensions: `admin-action-extender: NotesAdminActionExtenderContribution`.
- Permissions: `View:Note`, `Resolve:Note`, `Reopen:Note`, `CompleteAssignment:Note`.
- Public routes: none declared.
- Database changes: package migrations are declared.
- Config: `config/capell-notes.php`.
- Settings: no package settings declared.
- Queues or schedules: scheduled commands `capell:notes:send-due-reminders (everyFiveMinutes)`.
- Cache tags: none declared.
- Commands: `capell:notes-demo`, `capell:notes:send-due-reminders`.

## Common Pitfalls

- Keep required Capell packages on compatible v4 releases: `capell-app/admin`.
- Run migrations before opening package resources or public routes.
- Review package configuration before production-like verification: `config/capell-notes.php`.
- Register the host scheduler so these declared commands run at their documented frequencies: `capell:notes:send-due-reminders (everyFiveMinutes)`.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or declared schedule is not active | Check the jobs and scheduled commands listed in `Technical Shape` | Start the queue worker or host scheduler, then run the focused command or package test |

## Quick Start

1. Install the package: `composer require capell-app/notes`.
2. Run the required setup: `php artisan migrate`.
3. Open the User-menu notes item with attention badge and confirm the admin workflow loads.

## Next Steps

- [Package docs](docs/README.md)
- [Overview](docs/overview.md)
- [Admin guide](docs/admin-guide.md)
- Configuration files: [`config/capell-notes.php`](config/capell-notes.php).
- [Troubleshooting](#troubleshooting)
- [Screenshot contract](docs/screenshots.json)
- [Marketplace assets](docs/assets/marketplace/)
- [Capell content language plan](../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../docs/erd/capell-and-package-erds.md)
- Focused tests: `vendor/bin/pest packages/notes/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
