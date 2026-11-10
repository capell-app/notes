# Notes reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/notes`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Notes is an **Available**, **Schema-owning** Capell package in the **Capell Engagement & CRM** product group. It ships as `capell-app/notes` and extends these surfaces: admin.

- Status: Available
- Tier: free
- Bundle: engagement-crm
- Composer package: `capell-app/notes`
- Namespace: `Capell\Notes`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Notes\Providers\NotesServiceProvider`
- `Capell\Notes\Providers\MigrationMetadataServiceProvider`
- `Capell\Notes\Providers\ConsoleServiceProvider`
- `Capell\Notes\Providers\AdminServiceProvider`

### Config files

- `packages/notes/config/capell-notes.php`

### Migrations

- `packages/notes/database/migrations/2026_05_10_190862_01_create_notes_tables.php`
- `packages/notes/database/migrations/2026_07_10_000002_encrypt_note_bodies.php`
- `packages/notes/database/migrations/2026_10_22_000001_add_note_reminder_schedule_state.php`

### Models

- `Note`
- `NoteAssignment`
- `NoteMention`
- `NoteReminder`

### Filament classes

- `CreateNoteResourceHeaderActionExtender`
- `ReminderDateTimePicker`
- `NotesInboxPage`

### Policies

- `NotePolicy`

### Actions

- `AssignNoteUsersAction`
- `BuildSubjectNotesAction`
- `BuildUserAttentionCountsAction`
- `BuildUserInboxNotesAction`
- `CalculateNextNoteReminderDueAction`
- `CanViewNoteAction`
- `CompleteNoteAssignmentAction`
- `CreateNoteAction`
- `MarkNoteMentionsReadAction`
- `MentionNoteUsersAction`
- `PrepareEmptyNotesScreenshotInboxAction`
- `PruneNotesForDeletedParticipantAction`
- `PruneNotesForDeletedSubjectAction`
- `ReopenNoteAction`
- `ResolveNoteAction`
- `ResolveNoteParticipantsAction`
- `ResolveNoteReminderRecipientsAction`
- `SendDueNoteReminderNotificationsAction`
- `SendNoteAssignmentNotificationsAction`
- `SendNoteMentionNotificationsAction`
- `UpsertNoteReminderAction`

### Data objects

- `CreateNoteData`
- `NoteReminderData`
- `UserAttentionCountData`

### Command signatures

- `capell:notes-demo`
- `capell:notes:send-due-reminders`

### Scheduled commands

- `capell:notes:send-due-reminders (everyFiveMinutes; package registered)`

### Console command classes

- `DemoCommand`
- `SendDueNoteRemindersCommand`

### Manifest contributions

- `admin-action-extender: Capell\Notes\Manifest\NotesAdminActionExtenderContribution`
- `admin-page: Capell\Notes\Manifest\NotesAdminPageContribution`
- `console-command: Capell\Notes\Manifest\NotesConsoleCommandsContribution`
- `health-check: Capell\Notes\Manifest\NotesHealthContribution`
- `model: Capell\Notes\Manifest\NotesModelsContribution`
- `scheduled-job: Capell\Notes\Manifest\NotesReminderScheduleContribution`

### Health checks

- `Capell\Notes\Health\NotesHealthCheck`

### Blade views

- `packages/notes/resources/views/filament/pages/notes-inbox.blade.php`


## Data Model

- Required tables: `notes`, `note_assignments`, `note_mentions`, `note_reminders`.
- Models: `Note`, `NoteAssignment`, `NoteMention`, `NoteReminder`.
- Migration files: `2026_05_10_190862_01_create_notes_tables.php`, `2026_07_10_000002_encrypt_note_bodies.php`, `2026_10_22_000001_add_note_reminder_schedule_state.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare cascade-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
