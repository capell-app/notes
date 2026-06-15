# Notes

<!-- prettier-ignore-start -->

## What This Plugin Adds

Notes is an **Available**, **Schema-owning** Capell package in the **Capell Collaboration** product group. It ships as `capell-app/notes` and extends these surfaces: admin.

Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention.

After install, admins get the Notes inbox page, user-menu attention badge, record-level Add note header action, demo command, and scheduled reminder command inside Capell Admin.

Status details:

- Status: Available
- Tier: premium
- Bundle: collaboration
- Composer package: `capell-app/notes`
- Namespace: `Capell\Notes`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, models, Filament classes, and Blade views instead of pushing this behaviour into core or application code.

**For teams:** Add private, assignable notes and @mentions to any Capell admin record so editors can leave context, hand off work, and never lose track of what needs attention.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- User-menu notes item with attention badge (admin, required).
- Notes inbox page (admin, required).
- Record-level Add note modal (admin, required).
- Empty notes inbox state (admin, required).
- Notes inbox with assigned, mentioned, and lifecycle controls (admin, required).

## Technical Shape

- Service providers: `Capell\Notes\Providers\NotesServiceProvider`, `Capell\Notes\Providers\AdminServiceProvider`.
- Config files: `packages/notes/config/capell-notes.php`.
- Migrations: `packages/notes/database/migrations/2026_05_10_190862_01_create_notes_tables.php`.
- Models: `Note`, `NoteAssignment`, `NoteMention`, `NoteReminder`.
- Filament classes: `CreateNoteResourceHeaderActionExtender`, `NotesInboxPage`.
- Actions: `AssignNoteUsersAction`, `BuildSubjectNotesAction`, `BuildUserAttentionCountsAction`, `BuildUserInboxNotesAction`, `CanViewNoteAction`, `CompleteNoteAssignmentAction`, `CreateNoteAction`, `MarkNoteMentionsReadAction`, `MentionNoteUsersAction`, `PruneNotesForDeletedParticipantAction`, `PruneNotesForDeletedSubjectAction`, `ReopenNoteAction`, `and 5 more`.
- Data objects: `CreateNoteData`, `NoteReminderData`, `UserAttentionCountData`.
- Command signatures: `capell:notes-demo`.
- Console command classes: `DemoCommand`, `SendDueNoteRemindersCommand`.
- Manifest contributions: `admin-action-extender: Capell\Notes\Manifest\NotesAdminActionExtenderContribution`, `admin-page: Capell\Notes\Manifest\NotesAdminPageContribution`, `console-command: Capell\Notes\Manifest\NotesConsoleCommandsContribution`, `health-check: Capell\Notes\Manifest\NotesHealthContribution`, `model: Capell\Notes\Manifest\NotesModelsContribution`, `scheduled-job: Capell\Notes\Manifest\NotesReminderScheduleContribution`.
- Health checks: `Capell\Notes\Health\NotesHealthCheck`.
- Blade views: `packages/notes/resources/views/filament/pages/notes-inbox.blade.php`.

## Data Model

- Models: `Note`, `NoteAssignment`, `NoteMention`, `NoteReminder`.
- Required tables: `notes`, `note_assignments`, `note_mentions`, `note_reminders`.
- Migration files: `2026_05_10_190862_01_create_notes_tables.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap unless the package has an explicit pruning command, retention setting, or tested cascade path.

## Install Impact

- Admin navigation: adds the Notes inbox page and user-menu attention badge when registered.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: package migrations are declared.
- Settings: no package settings declared.
- Queues or schedules: `capell:notes:send-due-reminders` runs every five minutes when reminder scheduling is enabled.
- Cache tags: none declared.
- Commands: `capell:notes-demo`, `capell:notes:send-due-reminders`.

## Common Pitfalls

- Run migrations before opening package resources or public routes.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or scheduled command is not active | Check package jobs, commands, and host scheduler configuration | Start the queue or scheduler, then run the focused command or package test |

## Quick Start

1. Install the package: `composer require capell-app/notes`.
2. Run the host application's package install and migration flow.
3. Open the related Capell admin surface and verify Notes appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Focused tests: `vendor/bin/pest packages/notes/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
