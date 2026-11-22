<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateNoteBodyAction
{
    use AsFake;
    use AsObject;

    /**
     * Replaces the note body and keeps the previous body as a revision, so the
     * edit history shows what the note said before each change.
     */
    public function handle(Note $note, string $body, Model $actor): Note
    {
        Gate::forUser($actor)->authorize('edit', $note);

        $body = trim($body);

        if ($body === '' || $body === $note->body) {
            return $note;
        }

        DB::transaction(function () use ($note, $body, $actor): void {
            $note->revisions()->create([
                'body' => $note->body,
                'edited_by_type' => $actor->getMorphClass(),
                'edited_by_id' => $actor->getKey(),
            ]);

            $note->forceFill(['body' => $body])->save();
        });

        return $note;
    }
}
