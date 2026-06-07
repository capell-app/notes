<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Model $participant)
 */
final class PruneNotesForDeletedParticipantAction
{
    use AsObject;

    public function handle(Model $participant): void
    {
        DB::transaction(function () use ($participant): void {
            Note::query()
                ->where('author_type', $participant->getMorphClass())
                ->where('author_id', $participant->getKey())
                ->get()
                ->each(static function (Note $note): void {
                    $note->delete();
                });

            NoteAssignment::query()
                ->where('assignee_type', $participant->getMorphClass())
                ->where('assignee_id', $participant->getKey())
                ->delete();

            NoteAssignment::query()
                ->where('assigned_by_type', $participant->getMorphClass())
                ->where('assigned_by_id', $participant->getKey())
                ->update([
                    'assigned_by_type' => null,
                    'assigned_by_id' => null,
                ]);

            NoteMention::query()
                ->where('mentioned_type', $participant->getMorphClass())
                ->where('mentioned_id', $participant->getKey())
                ->delete();

            NoteMention::query()
                ->where('mentioned_by_type', $participant->getMorphClass())
                ->where('mentioned_by_id', $participant->getKey())
                ->update([
                    'mentioned_by_type' => null,
                    'mentioned_by_id' => null,
                ]);
        });
    }
}
