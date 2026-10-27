<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?NoteReminder run(Note $note, ?NoteReminderData $data)
 */
final class UpsertNoteReminderAction
{
    use AsFake;
    use AsObject;

    public function handle(Note $note, ?NoteReminderData $data): ?NoteReminder
    {
        if (! $data instanceof NoteReminderData || ! $data->dueAt instanceof CarbonImmutable) {
            $note->reminder()->delete();

            return null;
        }

        if (config('capell-notes.reminders.timezone_schedules_enabled', false) !== true) {
            throw ValidationException::withMessages(['reminder_due_at' => __('capell-notes::note.validation.reminders_unavailable')]);
        }

        Validator::make(['reminder_timezone' => $data->timezone], ['reminder_timezone' => ['required', 'timezone:all']])->validate();
        if (ResolveNoteReminderRecipientsAction::run($note) === []) {
            throw ValidationException::withMessages(['assignee_ids' => __('capell-notes::note.validation.reminder_recipient_required')]);
        }

        $dueAt = $data->dueAt->utc();

        /** @var NoteReminder $reminder */
        $reminder = $note->reminder()->updateOrCreate(
            ['note_id' => $note->getKey()],
            [
                // Set the storage version before casting any instants, including replacements.
                'schedule_version' => NoteReminder::UTC_SCHEDULE_VERSION,
                'due_at' => $dueAt,
                'timezone' => $data->timezone,
                'delivery_failed_at' => null,
                'recurrence' => $data->recurrence,
                'next_due_at' => $dueAt,
                'last_notified_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
            ],
        );

        return $reminder;
    }
}
