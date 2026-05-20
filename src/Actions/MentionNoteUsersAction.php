<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Support\NotesManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class MentionNoteUsersAction
{
    use AsObject;

    /**
     * @param  list<Model>  $mentions
     */
    public function handle(Note $note, array $mentions, ?Model $mentionedBy = null): void
    {
        $notes = resolve(NotesManager::class);

        foreach ($mentions as $mentioned) {
            $notes->ensureParticipant($mentioned);
        }

        if ($mentionedBy instanceof Model) {
            $notes->ensureParticipant($mentionedBy);
        }

        DB::transaction(function () use ($note, $mentions, $mentionedBy): void {
            $timestamp = now();
            $rows = collect($mentions)
                ->map(fn (Model $mentioned): array => [
                    'note_id' => $note->getKey(),
                    'mentioned_type' => $mentioned->getMorphClass(),
                    'mentioned_id' => $mentioned->getKey(),
                    'mentioned_by_type' => $mentionedBy?->getMorphClass(),
                    'mentioned_by_id' => $mentionedBy?->getKey(),
                    'read_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])
                ->values()
                ->all();

            if ($rows === []) {
                return;
            }

            $note->mentions()->upsert(
                $rows,
                ['note_id', 'mentioned_type', 'mentioned_id'],
                [
                    'mentioned_by_type',
                    'mentioned_by_id',
                    'read_at',
                    'updated_at',
                ],
            );
        });
    }
}
