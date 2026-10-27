# Deploying timezone-aware Notes reminders

Retire every old reminder producer and scheduler before allowing version-3 writes. The additive migration preserves existing dates and notification stamps and marks existing rows as version 1. New and replaced reminders use version 3: all schedule and delivery instants are stored as UTC, while recurrence uses the selected reminder timezone. This distinguishes both occurrences of an autumn clock overlap, even when the application timezone observes daylight saving.

Versions 1 and 2 retain their existing application-zone storage interpretation. Version 1 retains the legacy application-clock cadence; version 2 retains the selected-zone cadence from the preceding implementation. Already ambiguous version-1/2 dates cannot be reconstructed automatically: verify the intended instant with the owner and replace the reminder through the normal Action. Do not relabel an existing row as version 3; replacing it writes the UTC anchor and next occurrence and clears previous delivery state.

An old scheduler misinterprets version-3 UTC dates as application-clock dates, and older recurrence logic also advances from processing time. For example, processing a 09:00 New York daily reminder at 14:30 UTC on 7 March 2026 would move it to 10:30 EDT on 8 March, rather than 09:00 EDT.

## Upgrade sequence

1. Quiesce reminder producers across all nodes: block web writes, stop integration/CLI writers and drain or pause workers that create or replace reminders. Stop every Notes scheduler trigger, including cron, long-running scheduler processes and direct command invocations. Wait for in-flight producers and scheduler runs to finish. The existing `capell-notes.reminders.schedule_enabled` setting suppresses registration on newly booted processes; it does not stop an already-running process or a direct command.
2. Take a recoverable database backup and record the application/storage timezone. Keep that timezone unchanged throughout the rollout. In the staged release, leave `CAPELL_NOTES_TIMEZONE_SCHEDULES_ENABLED=false` and complete package migrations against the shared database before switching traffic. Do not run the new scheduler against an unmigrated database.
3. Switch every web, CLI, integration, queue-worker and scheduler node to compatible code. Restart long-running processes and rebuild their configuration caches. Confirm that no old process or scheduled command can start again, and that each runtime has the new code, migrated schema and disabled activation setting.
4. Only after those checks, set `CAPELL_NOTES_TIMEZONE_SCHEDULES_ENABLED=true` everywhere (or explicitly set `capell-notes.reminders.timezone_schedules_enabled` to boolean `true` in a published configuration). Rebuild configuration caches and restart workers so every producer sees the same setting. Missing, false or non-boolean activation rejects creation/replacement through `UpsertNoteReminderAction` with a validation error; note creation rolls back without participant notifications. Reminder removal remains available. Integrations must use this Action rather than writing schedule rows directly.
5. Resume compatible producers and workers, then resume compatible schedulers. Verify a due reminder reaches an eligible assignee and that its next occurrence retains the selected local wall time. Existing version-1 reminders retain the legacy application-clock cadence; new/replaced reminders use version 3 (UTC storage). Monitor failed queue jobs separately from scheduler acceptance.

On a fresh install the same activation gate applies. An operator must confirm that the migrated application and all scheduler/worker nodes are compatible before enabling writes. Existing reminders can still be processed by the new scheduler while activation is disabled; the flag gates writes, not dispatch.

## Rollback

First quiesce producers, scheduler triggers and in-flight work as above. Disable activation on all compatible nodes, rebuild configuration caches and restart long-running processes. This prevents additional version-3 writes; it does not convert existing rows.

Before any timezone-aware schedule data exists, roll back application code only after confirming there are no `note_reminders` rows with `schedule_version >= 2`, including completed or cancelled rows. Leave the additive columns in place and preserve the application/storage timezone; do not run the destructive down migration against operational data.

After any version-2 or version-3 row exists, do not resume an old scheduler or old reminder producer against that database. Prefer a forward fix, or keep the compatible reminder runtime deployed while the rest of the application rolls back. If a full rollback is unavoidable, retain the quiesced state until the owner approves and verifies a data recovery plan, such as restoring the pre-upgrade backup with its intervening-write loss explicitly accounted for. Merely disabling the flag, relabelling version-2/3 rows as version 1, or dropping the new columns does not preserve their timezone cadence. Resume only when the code and restored data are compatible on every node.

A rollback to the preceding version-2 implementation is also unsafe after any version-3 row exists: its casts and scheduler use application-zone storage. Keep a version-3-compatible reminder runtime or use the approved recovery plan above.
