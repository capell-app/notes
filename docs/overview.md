# Notes

<!-- prettier-ignore-start -->

## What it does

Notes adds encrypted, admin-only collaboration notes to supported records. Pages are supported by default; another package must explicitly register its model and edit page before the **Add note** action appears there. Notes never render on the public site.

## Where to use it

- Open a supported record's edit page and select **Add note** to create context, assign editors, mention people, and set an optional reminder.
- Open **Notes** in the Extensions navigation or user menu to see notes you authored, active assignments, and mentions that you are still allowed to view. The inbox shows the 25 most recently updated eligible notes and can filter Open or Resolved records; it is not a complete paginated archive.
- The user-menu badge counts active assignments, due/overdue reminders, and unread mentions. Opening the inbox marks the mentions in the loaded inbox set as read.

## Visibility and access

Creating a note requires update access to its subject record. Every selected assignee and mentioned user must also be able to update that record at creation time; users outside the record's Site/access scope are excluded.

Visibility has two levels:

- **Record editors** allows the author, active assignees, mentioned users, and any other user who can currently update the subject to read the note.
- **Private** allows only the author, active assignees, and mentioned users, and each of them must still be able to update the subject.

A completed assignee is no longer an active participant unless they are also the author or mentioned. Losing update access to the subject removes the note from the inbox and prevents notification delivery even if an assignment or mention row still exists. Note access is therefore governed by the subject's policy and participation, not by a separate public or cross-Site sharing link.

Note bodies are limited to 5,000 characters. Assignment, mention, reminder, and status metadata can be used for workflow, but Notes has no file attachments or public comments surface.

## Assignments and lifecycle

Assignment and mention creation queue a notification for each eligible recipient except the actor assigning/mentioning themselves. An assignee completes only their own assignment; that does not resolve the note or complete another assignee's work.

The author or an active assignee can resolve and reopen a note. Resolving records the resolution time but does not complete assignments or cancel/complete its reminder. Complete the relevant assignment and remove or replace the reminder through the owning integration when follow-up should stop; otherwise an active recurring reminder can continue to notify after resolution.

Notes are hard-deleted with their lifecycle owner rather than soft-deleted. Deleting a registered subject deletes all of its notes, assignments, mentions, and reminders. Deleting an author deletes notes they authored; deleting another participant removes their assignments/mentions and preserves the remaining note. There is no manual Notes trash screen or age-based retention job.

## Notifications and reminders

Notifications implement Laravel's queued notification contract, so run a queue worker as well as the scheduler. The default channel is the database notification channel. It is silently omitted when the notifications table is unavailable; mail or other supported channels must be deliberately configured in `capell-notes.notifications.channels`.

With `capell-notes.reminders.schedule_enabled` enabled, the scheduler runs `capell:notes:send-due-reminders` every five minutes with overlap and single-server protection. Each run processes up to 100 due reminder records by default; a reminder can notify several active assignees. A backlog is handled by later runs. `--limit` changes the number of reminder records selected.

One-off, daily, weekly, monthly, and yearly recurrence is supported. Recurring next dates are calculated from the time the reminder run processes them, not from the original due time, so delayed scheduler runs shift the later cadence. The stored timezone is descriptive; due parsing and recurrence use the application clock, so keep the application timezone and clock correct.

Only active assignees who still have subject access receive reminder notifications. The reminder is marked notified, and a recurring date is advanced, after notification jobs are queued even if there are no eligible recipients. A later queue-delivery failure does not make the scheduler retry that one-off occurrence; Notes declares no package-specific tries or backoff. Monitor failed queue jobs and recreate/resend the reminder when delivery evidence is required.

## Data and privacy

The note body is encrypted at rest with the application key. Subject, author, assignee and mention model references, statuses, reminder dates/timezones, and notification type/note ID remain plaintext operational metadata; database notification payloads do not copy the note body.

Notes does not register a Privacy Center exporter or eraser. Treat notes as internal records that may contain personal or commercially sensitive information, restrict subject edit access accordingly, and include the Notes tables and backups in the owning retention/subject-request process.

---

For how to use Notes, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
