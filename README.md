# Capell Notes

Contextual notes, assignments, mentions, and reminders for Capell admin records.

## Admin Surface

When installed, Notes registers a Filament extension page and a user-menu item. The menu item links to the Notes inbox and shows a badge with the current user's open assignments, unread mentions, due-today reminders, and overdue reminders.

## Registering Note Targets

Notes are polymorphic, but writes are deliberately constrained. A model must be registered as a note subject before notes can be attached to it, and a model must be registered as a participant before it can author, receive, or be mentioned in notes.

```php
use Capell\Notes\Facades\CapellNotes;
use App\Models\User;
use App\Models\Page;

CapellNotes::registerSubject(Page::class);
CapellNotes::registerParticipant(User::class);
```

The package registers the configured auth user model as a participant by default. Subject models are not registered automatically because each package should opt in only the admin records that can safely expose notes.

## Domain Actions

Use the package actions rather than writing note rows directly:

- `CreateNoteAction`
- `AssignNoteUsersAction`
- `MentionNoteUsersAction`
- `CompleteNoteAssignmentAction`
- `ResolveNoteAction`
- `ReopenNoteAction`
- `BuildUserAttentionCountsAction`

Assignments and mentions are idempotent. Reassigning or re-mentioning a user reactivates the item by clearing `completed_at` or `read_at`.
