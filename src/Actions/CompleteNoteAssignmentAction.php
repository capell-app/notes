<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class CompleteNoteAssignmentAction
{
    use AsFake;
    use AsObject;

    public function handle(Note $note, Model $assignee): void
    {
        Gate::forUser($assignee)->authorize('completeAssignment', $note);

        $note->assignments()
            ->where('assignee_type', $assignee->getMorphClass())
            ->where('assignee_id', $assignee->getKey())
            ->update(['completed_at' => now()]);
    }
}
