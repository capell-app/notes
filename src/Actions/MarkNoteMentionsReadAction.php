<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteMention;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

final class MarkNoteMentionsReadAction
{
    use AsObject;

    /**
     * @param  iterable<Note>  $notes
     */
    public function handle(Model $user, iterable $notes): int
    {
        $noteIds = [];

        foreach ($notes as $note) {
            if ($note instanceof Note && $note->exists) {
                $noteIds[] = (int) $note->getKey();
            }
        }

        $noteIds = array_values(array_unique($noteIds));

        if ($noteIds === []) {
            return 0;
        }

        return NoteMention::query()
            ->where('mentioned_type', $user->getMorphClass())
            ->where('mentioned_id', $user->getKey())
            ->whereIn('note_id', $noteIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
