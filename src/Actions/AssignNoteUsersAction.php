<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class AssignNoteUsersAction
{
    use AsObject;

    /**
     * @param  list<Model>  $assignees
     */
    public function handle(Note $note, array $assignees, ?Model $assignedBy = null): void
    {
        $notes = resolve(NotesManager::class);

        foreach ($assignees as $assignee) {
            $notes->ensureParticipant($assignee);
        }

        if ($assignedBy instanceof Model) {
            $notes->ensureParticipant($assignedBy);
        }

        DB::transaction(function () use ($note, $assignees, $assignedBy): void {
            $timestamp = now();
            $rows = collect($assignees)
                ->map(fn (Model $assignee): array => [
                    'note_id' => $note->getKey(),
                    'assignee_type' => $assignee->getMorphClass(),
                    'assignee_id' => $assignee->getKey(),
                    'assigned_by_type' => $assignedBy?->getMorphClass(),
                    'assigned_by_id' => $assignedBy?->getKey(),
                    'completed_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->values()
                ->all();

            if ($rows === []) {
                return;
            }

            $note->assignments()->upsert(
                $rows,
                ['note_id', 'assignee_type', 'assignee_id'],
                [
                    'assigned_by_type',
                    'assigned_by_id',
                    'completed_at',
                    'updated_at',
                ],
            );
        });
    }
}
