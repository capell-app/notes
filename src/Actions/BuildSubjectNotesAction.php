<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Enums\NoteVisibility;
use Capell\Notes\Models\Note;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, Note> run(Model $subject, Model $user, ?NoteStatus $status = null, int $limit = 25)
 */
final class BuildSubjectNotesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return Collection<int, Note>
     */
    public function handle(Model $subject, Model $user, ?NoteStatus $status = null, int $limit = 25): Collection
    {
        // Every note here shares one subject, so the subject policy is answered once
        // and note visibility is decided in SQL: only the requested page is read.
        if (! Gate::forUser($user)->allows('update', $subject)) {
            return new Collection;
        }

        $notes = Note::query()
            ->with([
                'assignments.assignee',
                'author',
                'mentions.mentioned',
                'subject',
            ])
            ->whereMorphedTo('subject', $subject)
            ->when($status instanceof NoteStatus, fn (Builder $query): Builder => $query->where('status', $status))
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('visibility', '!=', NoteVisibility::Private->value)
                    ->orWhereMorphedTo('author', $user)
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
            ->limit(max(1, $limit))
            ->get();

        return new Collection($notes
            ->filter(fn (Note $note): bool => CanViewNoteAction::run($note, $user))
            ->values()->all());
    }
}
