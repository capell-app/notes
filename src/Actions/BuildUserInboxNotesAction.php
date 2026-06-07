<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, Note> run(Model $user, ?NoteStatus $status = null, int $limit = 25)
 */
final class BuildUserInboxNotesAction
{
    use AsObject;

    /**
     * @return Collection<int, Note>
     */
    public function handle(Model $user, ?NoteStatus $status = null, int $limit = 25): Collection
    {
        return Note::query()
            ->with([
                'assignments.assignee',
                'author',
                'mentions.mentioned',
                'subject',
            ])
            ->when($status instanceof NoteStatus, fn (Builder $query): Builder => $query->where('status', $status))
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereMorphedTo('author', $user)
                    ->orWhereHas('assignments', function (Builder $assignmentQuery) use ($user): void {
                        $assignmentQuery
                            ->where('assignee_type', $user->getMorphClass())
                            ->where('assignee_id', $user->getKey())
                            ->whereNull('completed_at');
                    })
                    ->orWhereHas('mentions', function (Builder $mentionQuery) use ($user): void {
                        $mentionQuery
                            ->where('mentioned_type', $user->getMorphClass())
                            ->where('mentioned_id', $user->getKey());
                    });
            })
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
