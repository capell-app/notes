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
 * @method static Collection<int, Note> run(Model $subject, Model $user, ?NoteStatus $status = null, int $limit = 25)
 */
final class BuildSubjectNotesAction
{
    use AsObject;

    /**
     * @return Collection<int, Note>
     */
    public function handle(Model $subject, Model $user, ?NoteStatus $status = null, int $limit = 25): Collection
    {
        $notes = Note::query()
            ->with([
                'assignments.assignee',
                'author',
                'mentions.mentioned',
                'subject',
            ])
            ->whereMorphedTo('subject', $subject)
            ->when($status instanceof NoteStatus, fn (Builder $query): Builder => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $notes
            ->filter(fn (Note $note): bool => CanViewNoteAction::run($note, $user))
            ->values();
    }
}
