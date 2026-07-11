<?php

declare(strict_types=1);

namespace Capell\Notes\Actions;

use Capell\Notes\Data\UserAttentionCountData;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Notes\Models\NoteReminder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static UserAttentionCountData run(Model $user)
 */
final class BuildUserAttentionCountsAction
{
    use AsObject;

    public function handle(Model $user): UserAttentionCountData
    {
        $startOfDay = today();
        $endOfDay = now()->endOfDay();

        return new UserAttentionCountData(
            assigned: NoteAssignment::query()
                ->with(['note.assignments', 'note.mentions', 'note.subject'])
                ->where('assignee_type', $user->getMorphClass())
                ->where('assignee_id', $user->getKey())
                ->whereNull('completed_at')
                ->get()
                ->filter(fn (NoteAssignment $assignment): bool => $assignment->note !== null
                    && CanViewNoteAction::run($assignment->note, $user))
                ->count(),
            dueToday: $this->visibleReminderCount($this->activeReminderQuery($user)
                ->where(function (Builder $query) use ($startOfDay, $endOfDay): void {
                    $query
                        ->whereBetween('next_due_at', [$startOfDay, $endOfDay])
                        ->orWhere(function (Builder $fallbackQuery) use ($startOfDay, $endOfDay): void {
                            $fallbackQuery
                                ->whereNull('next_due_at')
                                ->whereBetween('due_at', [$startOfDay, $endOfDay]);
                        });
                }), $user),
            overdue: $this->visibleReminderCount($this->activeReminderQuery($user)
                ->where(function (Builder $query) use ($startOfDay): void {
                    $query
                        ->where('next_due_at', '<', $startOfDay)
                        ->orWhere(function (Builder $fallbackQuery) use ($startOfDay): void {
                            $fallbackQuery
                                ->whereNull('next_due_at')
                                ->where('due_at', '<', $startOfDay);
                        });
                }), $user),
            mentions: NoteMention::query()
                ->with(['note.assignments', 'note.mentions', 'note.subject'])
                ->where('mentioned_type', $user->getMorphClass())
                ->where('mentioned_id', $user->getKey())
                ->whereNull('read_at')
                ->get()
                ->filter(fn (NoteMention $mention): bool => $mention->note !== null
                    && CanViewNoteAction::run($mention->note, $user))
                ->count(),
        );
    }

    /**
     * @return Builder<NoteReminder>
     */
    private function activeReminderQuery(Model $user): Builder
    {
        return NoteReminder::query()
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->whereHas('note.assignments', function (Builder $query) use ($user): void {
                $query
                    ->where('assignee_type', $user->getMorphClass())
                    ->where('assignee_id', $user->getKey())
                    ->whereNull('completed_at');
            });
    }

    /**
     * @param  Builder<NoteReminder>  $query
     */
    private function visibleReminderCount(Builder $query, Model $user): int
    {
        return $query
            ->with(['note.assignments', 'note.mentions', 'note.subject'])
            ->get()
            ->filter(fn (NoteReminder $reminder): bool => $reminder->note !== null
                && CanViewNoteAction::run($reminder->note, $user))
            ->count();
    }
}
