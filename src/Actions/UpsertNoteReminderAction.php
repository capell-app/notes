<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Data\NoteReminderData;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteReminder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ?NoteReminder run(Note $note, ?NoteReminderData $data)
 */
final class UpsertNoteReminderAction
{
    use AsObject;

    public function handle(Note $note, ?NoteReminderData $data): ?NoteReminder
    {
        if (! $data instanceof NoteReminderData || $data->dueAt === null) {
            $note->reminder()->delete();

            return null;
        }

        /** @var NoteReminder $reminder */
        $reminder = $note->reminder()->updateOrCreate(
            ['note_id' => $note->getKey()],
            [
                'due_at' => $data->dueAt,
                'timezone' => $data->timezone,
                'recurrence' => $data->recurrence,
                'next_due_at' => $data->dueAt,
                'last_notified_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
            ],
        );

        return $reminder;
    }
}
