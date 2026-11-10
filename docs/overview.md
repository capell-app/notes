# Notes

<!-- prettier-ignore-start -->

## What it does

Notes adds encrypted, admin-only collaboration notes to supported records. Pages are supported by default; another package must explicitly register its model and edit page before the **Add note** action appears there. Notes never render on the public site.

## Where to use it

- Open a supported record's edit page and select **Add note** to create context, assign editors, mention people, and set an optional reminder.
- Open **Notes** in the Extensions navigation or user menu to see notes you authored, active assignments, and mentions that you are still allowed to view. The inbox pages through permitted notes in sets of 25, with Previous and Next controls. Filter by status or attention (assigned, unread mentions, due today, overdue); full note instructions are displayed as escaped text before the available lifecycle buttons. Authorisation is applied before each page is filled, in bounded batches.
- The user-menu badge counts active assignments, due/overdue reminders, and unread mentions. Opening the inbox and paging through ordinary history marks displayed mentions as read. The unread-mentions filter retains its displayed results until you return to ordinary history; due-today and overdue filters use the application calendar, matching the badges.

## Visibility and access

Creating a note requires update access to its subject record. Every selected assignee and mentioned user must also be able to update that record at creation time; users outside the record's Site/access scope are excluded.

Visibility has two levels:

- **Record editors** allows the author, active assignees, mentioned users, and any other user who can currently update the subject to read the note.
- **Private** allows only the author, active assignees, and mentioned users, and each of them must still be able to update the subject.

A completed assignee is no longer an active participant unless they are also the author or mentioned. Losing update access to the subject removes the note from the inbox and prevents notification delivery even if an assignment or mention row still exists. Note access is therefore governed by the subject's policy and participation, not by a separate public or cross-Site sharing link.

Note bodies are limited to 5,000 characters. Assignment, mention, reminder, and status metadata can be used for workflow, but Notes has no file attachments or public comments surface.

## Assignments and lifecycle

Assignment and mention creation queue a notification for each eligible recipient except the actor assigning/mentioning themselves, after the outer transaction commits. Rejected or rolled-back creation emits no participant notifications. An assignee completes only their own assignment; that does not resolve the note or complete another assignee's work.

The author or an active assignee can resolve and reopen a note. Resolving records the resolution time but does not complete assignments or cancel/complete its reminder. Complete the relevant assignment and remove or replace the reminder through the owning integration when follow-up should stop; otherwise an active recurring reminder can continue to notify after resolution.

Notes are hard-deleted with their lifecycle owner rather than soft-deleted. Deleting a registered subject deletes all of its notes, assignments, mentions, and reminders. Deleting an author deletes notes they authored; deleting another participant removes their assignments/mentions and preserves the remaining note. There is no manual Notes trash screen or age-based retention job.

## Notifications and reminders

Notifications implement Laravel's queued notification contract, so run a queue worker as well as the scheduler. The default channel is the database notification channel. Database notifications use Filament's supported format, with generic translated text and a link to the Notes inbox; they never copy note content. It is silently omitted when the notifications table is unavailable; mail or other supported channels must be deliberately configured in `capell-notes.notifications.channels`.

With `capell-notes.reminders.schedule_enabled` enabled, the scheduler runs `capell:notes:send-due-reminders` every five minutes with overlap and single-server protection. Each run processes up to 100 due reminder records by default; a reminder can notify several active assignees. A backlog is handled by later runs. `--limit` changes the number of reminder records selected.

One-off, daily, weekly, monthly, and yearly recurrence is supported. New or replaced reminders interpret the selected date/time in the validated IANA timezone. Version-3 reminders store schedule and delivery instants as UTC, preserving both occurrences of an application-zone autumn overlap; recurrence still uses the selected reminder timezone. The inbox shows the effective next local time and UTC instant. Recurrence follows the original local wall time, across daylight-saving changes; a late scheduler sends one current reminder and advances to the first intended occurrence after processing, without cadence drift. Short months and leap years clamp to the final available day, retaining the original anchor for subsequent dates. PHP timezone rules advance nonexistent spring times by the gap and select one occurrence during an autumn overlap; recurring schedules never send twice for that overlap.

The scheduling-state migration leaves existing dates and notification stamps untouched and marks them as version 1. Their timezone text stays descriptive: recurrence continues from the already scheduled occurrence in the application timezone, without interpreting old timezone strings. Existing version-2 reminders retain application-zone storage and selected-zone recurrence. New or replaced reminders are version 3, with UTC storage and selected-zone recurrence. Existing ambiguous version-1/2 dates require the owner to confirm the intended instant and replace the reminder; they cannot be recovered by relabelling the version. Before upgrading, follow the [reminder deployment and rollback runbook](deployment.md). New and replacement reminders stay disabled until the operator explicitly activates timezone schedules after retiring every old reminder producer and scheduler. Migrating alone does not make mixed scheduler versions safe. Keep the application/storage timezone stable, as for other existing Eloquent timestamps.

Only active assignees who still have subject access receive reminder notifications, including a self-assigned author. Creating or replacing a reminder requires at least one eligible assignee; selecting only mentions is insufficient. If all assignees complete their work or lose access before dispatch, the occurrence stays due and unsent, with no notification stamp or cadence advancement. The inbox shows a warning, and the author receives one generic recovery notification if they still have subject access. Review the note in Notes; ask an administrator to restore access or change the assignment/reminder through the integration that created it. The Notes inbox has no assignment or reminder editor. Later runs retry without repeating the owner warning; unattempted due reminders take priority so unsent work cannot starve the backlog. Restoring an eligible assignment allows automatic recovery. Successful one-offs are completed; recurring dates advance only after recipient notification jobs are accepted. A later queue-delivery failure does not make the scheduler retry that one-off occurrence; Notes declares no package-specific tries or backoff. Monitor failed queue jobs and recreate/resend the reminder when delivery evidence is required.

## Data and privacy

The note body is encrypted at rest with the application key. Subject, author, assignee and mention model references, statuses, reminder dates/timezones, and notification type/note ID remain plaintext operational metadata; database notification payloads do not copy the note body.

Notes does not register a Privacy Centre exporter or eraser. Treat notes as internal records that may contain personal or commercially sensitive information, restrict subject edit access accordingly, and include the Notes tables and backups in the owning retention/subject-request process.

---

For how to use Notes, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
