<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Enums\NoteStatus;
use Capell\Notes\Models\Note;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, Note> run(Model $user, ?NoteStatus $status = null, int $limit = 25, int $offset = 0, string $attention = 'all')
 */
final class BuildUserInboxNotesAction
{
    use AsFake;
    use AsObject;

    /**
     * @return Collection<int, Note>
     */
    public function handle(Model $user, ?NoteStatus $status = null, int $limit = 25, int $offset = 0, string $attention = 'all'): Collection
    {
        $query = Note::query()
            ->with([
                'assignments.assignee',
                'author',
                'mentions.mentioned',
                'subject',
                'reminder',
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
            ->when($attention === 'assigned', fn (Builder $query): Builder => $query->whereHas('assignments', fn (Builder $assignmentQuery): Builder => $assignmentQuery->whereMorphedTo('assignee', $user)->whereNull('completed_at')))
            ->when($attention === 'mentions', fn (Builder $query): Builder => $query->whereHas('mentions', fn (Builder $mentionQuery): Builder => $mentionQuery->whereMorphedTo('mentioned', $user)->whereNull('read_at')))
            ->when(in_array($attention, ['dueToday', 'overdue'], true), fn (Builder $query): Builder => $query
                ->whereHas('assignments', fn (Builder $assignmentQuery): Builder => $assignmentQuery->whereMorphedTo('assignee', $user)->whereNull('completed_at'))
                ->whereHas('reminder', function (Builder $reminderQuery) use ($attention): void {
                    $reminderQuery->whereNull('completed_at')->whereNull('cancelled_at')
                        ->where(fn (Builder $pending): Builder => $pending->whereNull('last_notified_at')->orWhereColumn('last_notified_at', '<', 'next_due_at'))
                        ->when(
                            $attention === 'overdue',
                            fn (Builder $due): Builder => $due->dueBefore(CarbonImmutable::today()),
                            fn (Builder $due): Builder => $due->dueBetween(CarbonImmutable::today(), CarbonImmutable::now()->endOfDay()),
                        );
                }))
            ->latest('updated_at')
            ->latest('id')
            ->lazy(100)
            ->filter(fn (Note $note): bool => CanViewNoteAction::run($note, $user))
            ->skip(max(0, $offset))
            ->take(max(1, $limit));

        return new Collection($query->all());
    }
}
